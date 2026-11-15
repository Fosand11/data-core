<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Assistant\HelpInstructions;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Ai\Providers\GeminiHelpResponder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiHelpResponderTest extends TestCase
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_a_help_verdict_returns_the_answer_text(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply("HELP\nPuedes subir un CSV o un Excel en Datos."), 200)]);

        $answer = $this->responder()->answer($this->question());

        $this->assertTrue($answer->isHelp);
        $this->assertSame('Puedes subir un CSV o un Excel en Datos.', $answer->text);
    }

    public function test_a_data_verdict_hands_the_message_back_to_the_generator(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply('DATA'), 200)]);

        $answer = $this->responder()->answer($this->question());

        $this->assertFalse($answer->isHelp);
        $this->assertSame('', $answer->text);
    }

    public function test_only_the_question_the_fact_sheet_and_the_rules_leave_the_application(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply("HELP\nOk."), 200)]);

        $this->responder()->answer(new HelpQuestion('¿Qué es esto?', '- hecho de prueba'));

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            $this->assertSame(self::ENDPOINT, $request->url());
            $this->assertSame(['test-secret'], $request->header('x-goog-api-key'));
            $this->assertStringNotContainsString('test-secret', $request->url());
            $this->assertStringNotContainsString('key=', $request->url());
            $this->assertSame('¿Qué es esto?', $payload['contents'][0]['parts'][0]['text']);
            $this->assertStringContainsString('- hecho de prueba', $payload['systemInstruction']['parts'][0]['text']);
            $this->assertStringContainsString('ONLY the FACTS', $payload['systemInstruction']['parts'][0]['text']);
            // A small, explicit output budget; no tools, no schema, no grounding.
            $this->assertSame(['maxOutputTokens' => 321], $payload['generationConfig']);
            $this->assertArrayNotHasKey('tools', $payload);
            $this->assertStringNotContainsString('tenant', $encoded);

            return true;
        });
    }

    public function test_the_model_cannot_make_the_page_do_anything_its_text_is_cleaned(): void
    {
        $dirty = "HELP\n**Puedes** exportar a `PDF`.\u{0007}\n\n\n\n# Título\n<script>alert(1)</script>";
        Http::fake([self::ENDPOINT => Http::response($this->reply($dirty), 200)]);

        $text = $this->responder()->answer($this->question())->text;

        $this->assertStringNotContainsString('**', $text);
        $this->assertStringNotContainsString('`', $text);
        $this->assertStringNotContainsString('# ', $text);
        $this->assertStringNotContainsString("\u{0007}", $text);
        $this->assertStringNotContainsString("\n\n\n", $text);
        // Markup is left as harmless text: the page shows it escaped, never as HTML.
        $this->assertStringContainsString('Puedes exportar a PDF.', $text);
    }

    public function test_text_that_looks_like_decoration_but_is_content_is_left_alone(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply("HELP\nUsa total_amount > 100 para filtrar y guarda tu_archivo_final.csv."), 200)]);

        $this->assertSame('Usa total_amount > 100 para filtrar y guarda tu_archivo_final.csv.', $this->responder()->answer($this->question())->text);
    }

    public function test_a_long_answer_is_cut_at_the_end_of_a_sentence(): void
    {
        $long = "HELP\n".str_repeat('Esta es una frase de relleno. ', 60);
        Http::fake([self::ENDPOINT => Http::response($this->reply($long), 200)]);

        $text = $this->responder()->answer($this->question())->text;

        $this->assertLessThanOrEqual(700, mb_strlen($text));
        $this->assertStringEndsWith('.', $text);
    }

    public function test_a_reply_that_ignores_the_format_is_shown_as_text_never_acted_upon(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply('Puedes pedirme un embudo o un radar.'), 200)]);

        $answer = $this->responder()->answer($this->question());

        $this->assertTrue($answer->isHelp);
        $this->assertSame('Puedes pedirme un embudo o un radar.', $answer->text);
    }

    public function test_thought_parts_are_not_part_of_the_answer(): void
    {
        $body = ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => 'razonamiento interno que no debe verse', 'thought' => true],
            ['text' => "HELP\nRespuesta visible."],
        ]]]]];
        Http::fake([self::ENDPOINT => Http::response($body, 200)]);

        $this->assertSame('Respuesta visible.', $this->responder()->answer($this->question())->text);
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: string}>
     */
    public static function failures(): array
    {
        return [
            'bad key' => [403, ['error' => ['message' => 'denied']], 'AI_AUTHENTICATION_FAILED'],
            'invalid key as 400' => [400, ['error' => ['message' => 'API key not valid. Please pass a valid API key.']], 'AI_AUTHENTICATION_FAILED'],
            'quota' => [429, ['error' => ['message' => 'quota']], 'AI_RATE_LIMITED'],
            'server error' => [503, ['error' => ['message' => 'down']], 'AI_UNAVAILABLE'],
            'unknown model' => [404, ['error' => ['message' => 'not found']], 'AI_UNAVAILABLE'],
            'blocked prompt' => [200, ['promptFeedback' => ['blockReason' => 'SAFETY']], 'AI_GENERATION_REFUSED'],
            'blocked answer' => [200, ['candidates' => [['finishReason' => 'SAFETY']]], 'AI_GENERATION_REFUSED'],
            'no candidates' => [200, ['candidates' => []], 'AI_INVALID_RESPONSE'],
            'empty text' => [200, ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '   ']]]]]], 'AI_INVALID_RESPONSE'],
            'only the verdict, no answer' => [200, ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'HELP']]]]]], 'AI_INVALID_RESPONSE'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('failures')]
    public function test_every_failure_becomes_a_classified_exception_the_caller_can_fall_back_from(int $status, array $body, string $code): void
    {
        Http::fake([self::ENDPOINT => Http::response($body, $status)]);

        try {
            $this->responder()->answer($this->question());
            $this->fail('An exception was expected.');
        } catch (AiGenerationException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }

    public function test_a_timeout_is_reported_as_one(): void
    {
        Http::fake([self::ENDPOINT => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        try {
            $this->responder(maxRetries: 0)->answer($this->question());
            $this->fail('An exception was expected.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_TIMEOUT', $exception->errorCode);
        }
    }

    public function test_a_missing_key_or_a_malformed_model_never_reaches_the_network(): void
    {
        Http::fake();

        foreach ([['', 'gemini-test-model'], ['key', '../../etc/passwd'], ['key', 'model?x=1']] as [$key, $model]) {
            try {
                $this->responder(key: $key, model: $model)->answer($this->question());
                $this->fail('An exception was expected.');
            } catch (AiGenerationException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
    }

    public function test_the_question_is_never_written_to_the_logs(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->reply("HELP\nOk."), 200)]);
        Log::spy();

        $this->responder()->answer(new HelpQuestion('mi secreto: contraseña-123', '- hecho'));

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            $this->assertSame('help', $context['result']);
            $this->assertStringNotContainsString('contraseña-123', $encoded);
            $this->assertStringNotContainsString('hecho', $encoded);

            return true;
        });
    }

    public function test_a_question_is_cleaned_and_cut_before_it_is_sent(): void
    {
        $question = HelpQuestion::from("hola\u{0000}\n\n   que\ttal ".str_repeat('x', 500), '- hecho', 40);

        $this->assertSame(40, mb_strlen($question->text));
        $this->assertStringStartsWith('hola que tal x', $question->text);
        $this->assertStringNotContainsString("\n", $question->text);
    }

    // --- helpers -----------------------------------------------------------------------------

    private function responder(string $key = 'test-secret', string $model = 'gemini-test-model', int $maxRetries = 0): GeminiHelpResponder
    {
        return new GeminiHelpResponder($key, $model, 5, $maxRetries, 321, app(HelpInstructions::class));
    }

    private function question(): HelpQuestion
    {
        return new HelpQuestion('¿Qué puedo hacer?', '- un hecho');
    }

    /**
     * @return array<string, mixed>
     */
    private function reply(string $text): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text]]]]]];
    }
}
