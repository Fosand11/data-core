<?php

namespace App\Http\Middleware;

use App\Domain\Ai\Assistant\HelpAssistant;
use App\Domain\Ai\Assistant\HelpIntent;
use App\Domain\Ai\Assistant\HelpKnowledge;
use App\Domain\Ai\Assistant\HelpModelBudget;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Assistant\QuestionTriage;
use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Ai\Contracts\HelpResponder;
use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Support\DatasetCatalogResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sits in front of the dashboard-generation routes. When the text is a question about the
 * app itself ("¿qué visuales tienes?", "sugiéreme algo") it answers instead of designing a
 * dashboard titled after the question:
 *
 *  1. from fixed content, when the rules recognise the question (free and instant);
 *  2. from the model, when it is shaped like a question, does not talk about the user's
 *     data and the rules did not recognise it (a short answer from a fact sheet, within a
 *     small budget). The model may say the message was really about the data, in which case
 *     it goes on to generation as usual;
 *  3. from fixed content again, when the model is off, out of budget or fails: a question
 *     about the product never becomes a dashboard.
 *
 * It also applies the generation rate limit itself, to everything that is not a help answer.
 * Laravel runs `throttle` ahead of route middleware, so listing both would charge every help
 * question to the allowance; a help answer must not use up the allowance for real requests.
 */
final class AnswerHelpQuestions
{
    public function __construct(
        private readonly HelpAssistant $assistant,
        private readonly DatasetCatalogResolver $catalogs,
        private readonly ThrottleRequests $throttle,
        private readonly QuestionTriage $triage,
        private readonly HelpResponder $responder,
        private readonly HelpKnowledge $knowledge,
        private readonly HelpModelBudget $budget,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $text = $request->input('prompt', $request->input('instruction'));

        if (! is_string($text)) {
            return $this->generate($request, $next);
        }

        $intent = HelpIntent::fromPrompt($text);

        // The catalog (a lookup) is only needed when the message could be a question.
        if ($intent === null && ! HelpIntent::isQuestionShaped($text)) {
            return $this->generate($request, $next);
        }

        // A change to an existing dashboard is asked on its own route; a new question may name a dataset.
        $dashboard = $request->route('dashboard');
        $refinement = $dashboard instanceof Dashboard;
        $dataset = $refinement
            ? ($dashboard->dataset_id === null ? null : $this->readyDataset($dashboard->dataset_id))
            : $this->readyDataset($request->input('dataset_id'));
        $catalog = $dataset === null ? null : $this->catalogs->forDataset($dataset);

        if ($intent !== null) {
            return back()->with('assistant', $this->assistant->reply($intent, $catalog, $dataset?->name, $refinement));
        }

        if (! (bool) config('dashboard-ai.help.enabled') || ! $this->triage->shouldAsk($text, $catalog)) {
            return $this->generate($request, $next);
        }

        $reply = $this->askTheModel($request, $text, $catalog, $dataset?->name, $refinement);

        // null: the model judged the message to be a request about the user's data.
        return $reply === null ? $this->generate($request, $next) : back()->with('assistant', $reply);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function askTheModel(Request $request, string $text, ?DatasetCatalog $catalog, ?string $datasetName, bool $refinement): ?array
    {
        $unsure = fn (): array => $this->assistant->unsure($catalog, $datasetName, $refinement);

        // No help implementation for this driver: do not spend the allowance on a call that cannot happen.
        if ($this->responder->provider() === 'unsupported' || $request->user() === null || ! $this->budget->allow($request->user())) {
            return $unsure();
        }

        try {
            $answer = $this->responder->answer(HelpQuestion::from(
                $text,
                $this->knowledge->sheet($catalog, $datasetName),
                (int) config('dashboard-ai.help.max_question_chars'),
            ));
        } catch (AiGenerationException) {
            return $unsure();
        }

        return $answer->isHelp ? $this->assistant->answer($answer->text, $catalog, $datasetName, $refinement) : null;
    }

    private function generate(Request $request, Closure $next): Response
    {
        // exactly three arguments: that is how ThrottleRequests recognises a named limiter
        return $this->throttle->handle($request, $next, 'dashboard-generation');
    }

    /**
     * The organization's own ready dataset with this id, or null (the model is tenant-scoped, so
     * another organization's id is simply not found).
     */
    private function readyDataset(mixed $id): ?Dataset
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        return Dataset::query()->with('columns')->where('status', DatasetStatus::Ready->value)->find($id);
    }
}
