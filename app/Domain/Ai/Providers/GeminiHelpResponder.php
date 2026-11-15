<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Assistant\HelpAnswer;
use App\Domain\Ai\Assistant\HelpInstructions;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Contracts\HelpResponder;
use App\Domain\Ai\Exceptions\AiGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Answers help questions with the Gemini API, from a fixed fact sheet.
 *
 * What leaves the application: the instructions, the fact sheet (descriptions of the product
 * and the names of the user's columns) and the question itself. No tenant or user identifiers,
 * no rows and no secrets; the API key travels in a header. The question is never logged and
 * never stored. The reply is untrusted text: it is cleaned and shortened here and is only ever
 * displayed as plain text.
 */
final readonly class GeminiHelpResponder implements HelpResponder
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const MAX_ANSWER_CHARS = 700;

    /** @var list<string> */
    private const REFUSAL_FINISH_REASONS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'];

    public function __construct(
        private string $apiKey,
        private string $model,
        private int $timeout,
        private int $maxRetries,
        private int $maxOutputTokens,
        private HelpInstructions $instructions,
    ) {}

    public function provider(): string
    {
        return 'gemini';
    }

    public function answer(HelpQuestion $question): HelpAnswer
    {
        $startedAt = hrtime(true);
        $result = 'failed';

        try {
            $this->assertConfigured();
            $answer = $this->parse($this->extractText($this->send($question)));
            $result = $answer->isHelp ? 'help' : 'data';

            return $answer;
        } catch (AiGenerationException $exception) {
            $result = $exception->errorCode;

            throw $exception;
        } catch (ConnectionException $exception) {
            $isTimeout = TransientHttpFailures::isTimeout($exception);
            $result = $isTimeout ? 'AI_TIMEOUT' : 'AI_UNAVAILABLE';

            throw new AiGenerationException($result, $isTimeout ? 'Gemini tardó demasiado en responder.' : 'No fue posible conectar con Gemini.', $exception);
        } catch (Throwable $exception) {
            $result = 'AI_UNAVAILABLE';

            throw new AiGenerationException('AI_UNAVAILABLE', 'El servicio de ayuda no está disponible temporalmente.', $exception);
        } finally {
            // The question is deliberately absent: it may contain anything the user typed.
            Log::info('Help AI provider request finished.', [
                'provider' => $this->provider(),
                'model' => $this->model,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'result' => $result,
            ]);
        }
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new AiGenerationException('AI_AUTHENTICATION_FAILED', 'La integración con Gemini no tiene una credencial configurada.');
        }

        // The model is part of the URL path, so it must be a plain model code.
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $this->model) !== 1) {
            throw new AiGenerationException('AI_UNAVAILABLE', 'El modelo de ayuda no está configurado correctamente.');
        }
    }

    private function send(HelpQuestion $question): Response
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout(max(1, $this->timeout))
            ->retry(
                max(1, $this->maxRetries + 1),
                fn (int $attempt): int => TransientHttpFailures::backoffMilliseconds($attempt),
                fn (Throwable $exception): bool => TransientHttpFailures::shouldRetry($exception, retryRateLimits: false),
                throw: false,
            )
            ->post("/models/{$this->model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $this->instructions->system($question->facts)]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $question->text]]]],
                'generationConfig' => ['maxOutputTokens' => max(1, $this->maxOutputTokens)],
            ]);
    }

    private function extractText(Response $response): string
    {
        $status = $response->status();

        if (! $response->successful()) {
            if ($status === 401 || $status === 403 || ($status === 400 && $this->isInvalidApiKey($response))) {
                throw new AiGenerationException('AI_AUTHENTICATION_FAILED', 'Gemini rechazó la credencial configurada.');
            }

            if ($status === 429) {
                throw new AiGenerationException('AI_RATE_LIMITED', 'Gemini alcanzó temporalmente su límite de solicitudes.');
            }

            throw new AiGenerationException($status === 404 || $response->serverError() ? 'AI_UNAVAILABLE' : 'AI_INVALID_RESPONSE', 'Gemini no pudo responder la pregunta.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new AiGenerationException('AI_INVALID_RESPONSE', 'Gemini devolvió una respuesta que no pudo procesarse.');
        }

        $blockReason = $body['promptFeedback']['blockReason'] ?? null;
        $candidate = $body['candidates'][0] ?? null;
        $finish = is_array($candidate) ? ($candidate['finishReason'] ?? null) : null;

        if ((is_string($blockReason) && $blockReason !== '') || (is_string($finish) && in_array($finish, self::REFUSAL_FINISH_REASONS, true))) {
            throw new AiGenerationException('AI_GENERATION_REFUSED', 'El modelo no pudo responder esta pregunta.');
        }

        if (! is_array($candidate)) {
            throw new AiGenerationException('AI_INVALID_RESPONSE', 'Gemini no devolvió una respuesta.');
        }

        $text = '';

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && ($part['thought'] ?? false) !== true) {
                $text .= $part['text'];
            }
        }

        if (trim($text) === '') {
            throw new AiGenerationException('AI_INVALID_RESPONSE', 'Gemini no devolvió texto.');
        }

        return $text;
    }

    /**
     * The first line says what the message was; the rest is the answer. A reply that ignores the
     * format is still shown as an answer (it is only text), but never acted upon.
     */
    private function parse(string $text): HelpAnswer
    {
        $parts = preg_split('/\R/u', trim($text), 2) ?: [''];
        $verdict = strtoupper(trim($parts[0], " \t*`:."));

        if ($verdict === 'DATA') {
            return HelpAnswer::aboutData();
        }

        $body = $verdict === 'HELP' ? ($parts[1] ?? '') : $text;
        $answer = $this->clean($body);

        if ($answer === '') {
            throw new AiGenerationException('AI_INVALID_RESPONSE', 'Gemini no devolvió una respuesta útil.');
        }

        return HelpAnswer::help($answer);
    }

    /**
     * Plain text only and short: control characters and Markdown decoration removed (bold, code
     * ticks, heading and quote markers at the start of a line), line breaks kept to at most one
     * blank line, and cut at the end of a sentence when it is too long. Characters that can be
     * part of real text (an underscore in a column name, a ">" in a comparison) are left alone.
     */
    private function clean(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text);
        $text = (string) preg_replace('/(\*\*|__)(.+?)\1/su', '$2', $text);
        $text = str_replace('`', '', $text);
        $text = (string) preg_replace('/^[ \t]*(?:#{1,6}|>)[ \t]*/mu', '', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = trim((string) preg_replace('/\n{3,}/u', "\n\n", str_replace("\r", '', $text)));

        if (mb_strlen($text) <= self::MAX_ANSWER_CHARS) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_ANSWER_CHARS);
        $end = max((int) mb_strrpos($cut, '.'), (int) mb_strrpos($cut, '?'), (int) mb_strrpos($cut, '!'));

        return $end > self::MAX_ANSWER_CHARS / 2 ? mb_substr($cut, 0, $end + 1) : rtrim($cut).'…';
    }

    private function isInvalidApiKey(Response $response): bool
    {
        $message = $response->json('error.message');

        return is_string($message) && stripos($message, 'API key not valid') !== false;
    }
}
