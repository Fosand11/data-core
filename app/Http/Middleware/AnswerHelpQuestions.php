<?php

namespace App\Http\Middleware;

use App\Domain\Ai\Assistant\HelpAssistant;
use App\Domain\Ai\Assistant\HelpIntent;
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
 * app itself ("¿qué visuales tienes?", "sugiéreme algo") it answers from fixed content and
 * sends the user back, instead of designing a dashboard titled after the question.
 *
 * It also applies the generation rate limit itself, to everything that is not a help answer.
 * Laravel runs `throttle` ahead of route middleware, so listing both would charge every help
 * question to the allowance; a help answer costs nothing and must not use up the allowance
 * for real requests.
 */
final class AnswerHelpQuestions
{
    public function __construct(
        private readonly HelpAssistant $assistant,
        private readonly DatasetCatalogResolver $catalogs,
        private readonly ThrottleRequests $throttle,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $text = $request->input('prompt', $request->input('instruction'));
        $intent = is_string($text) ? HelpIntent::fromPrompt($text) : null;

        if ($intent === null) {
            // exactly three arguments: that is how ThrottleRequests recognises a named limiter
            return $this->throttle->handle($request, $next, 'dashboard-generation');
        }

        // A change to an existing dashboard is asked on its own route; a new question may name a dataset.
        $dashboard = $request->route('dashboard');
        $dataset = $dashboard instanceof Dashboard
            ? ($dashboard->dataset_id === null ? null : $this->readyDataset($dashboard->dataset_id))
            : $this->readyDataset($request->input('dataset_id'));

        return back()->with('assistant', $this->assistant->reply(
            $intent,
            $dataset === null ? null : $this->catalogs->forDataset($dataset),
            $dataset?->name,
            refinement: $dashboard instanceof Dashboard,
        ));
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
