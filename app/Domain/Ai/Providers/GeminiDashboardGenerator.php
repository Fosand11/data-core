<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Contracts\ModelAwareDashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Ai\Prompts\DashboardGenerationInstructions;
use App\Domain\Ai\Schemas\DashboardSpecSchema;
use App\Domain\Ai\Schemas\ProviderSpecNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Generates a DashboardSpec with the Gemini API (Google AI Studio keys).
 *
 * Only the user's prompt, the static instructions and the schema leave the
 * application: no tenant or user identifiers, rows, SQL or secrets. The API key
 * travels in a header, never in the URL or in logs. The returned definition is
 * untrusted until DashboardSpecValidator accepts it.
 */
final readonly class GeminiDashboardGenerator implements DashboardGenerator, ModelAwareDashboardGenerator
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Finish reasons that mean the model declined or was blocked.
     *
     * @var list<string>
     */
    private const REFUSAL_FINISH_REASONS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'];

    public function __construct(
        private string $apiKey,
        private string $configuredModel,
        private int $timeout,
        private int $maxRetries,
        private int $maxOutputTokens,
        private DashboardSpecSchema $schema,
        private DashboardGenerationInstructions $instructions,
    ) {}

    public function provider(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->configuredModel;
    }

    public function generate(DashboardGenerationInput $input): DashboardSpec
    {
        $startedAt = hrtime(true);
        $result = 'failed';
        $usage = [];

        try {
            $this->assertConfigured();
            $response = $this->send($input);
            $usage = $this->usageFrom($response);
            $definition = $this->extractDefinition($response);
            $result = 'completed';

            return DashboardSpec::fromArray($definition);
        } catch (AiGenerationException $exception) {
            $result = $exception->errorCode;

            throw $exception;
        } catch (ConnectionException $exception) {
            $classified = $this->connectionFailure($exception);
            $result = $classified->errorCode;

            throw $classified;
        } catch (Throwable $exception) {
            $result = 'AI_UNAVAILABLE';

            throw new AiGenerationException(
                'AI_UNAVAILABLE',
                'El servicio de generación no está disponible temporalmente.',
                $exception,
            );
        } finally {
            Log::info('Dashboard AI provider request finished.', [
                'tenant_id' => $input->tenantId,
                'requested_by' => $input->requestedBy,
                'provider' => $this->provider(),
                'model' => $this->configuredModel,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'result' => $result,
                ...$usage,
            ]);
        }
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new AiGenerationException(
                'AI_AUTHENTICATION_FAILED',
                'La integración con Gemini no tiene una credencial configurada.',
            );
        }

        // The model is part of the URL path, so it must be a plain model code.
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $this->configuredModel) !== 1) {
            throw new AiGenerationException(
                'AI_UNAVAILABLE',
                'El modelo de generación no está configurado correctamente.',
            );
        }
    }

    private function send(DashboardGenerationInput $input): Response
    {
        try {
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
                ->post("/models/{$this->configuredModel}:generateContent", $this->payload($input));
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DashboardGenerationInput $input): array
    {
        return [
            'systemInstruction' => [
                'parts' => [['text' => $this->instructions->systemFor($input)]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $this->instructions->userMessageFor($input)]]],
            ],
            'generationConfig' => $this->generationConfig(),
        ];
    }

    /**
     * Structured output settings. Kept in one place: if Google renames the REST
     * fields (the docs also show `responseFormat.text.schema`), only this
     * method changes. No temperature is sent; newer Flash models ignore it.
     *
     * @return array<string, mixed>
     */
    private function generationConfig(): array
    {
        return [
            'responseMimeType' => 'application/json',
            'responseJsonSchema' => $this->schema->forGemini(),
            'maxOutputTokens' => max(1, $this->maxOutputTokens),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDefinition(Response $response): array
    {
        $this->assertSuccessfulStatus($response);

        $body = $response->json();

        if (! is_array($body)) {
            throw $this->invalidResponse('Gemini devolvió una respuesta que no pudo procesarse.');
        }

        $blockReason = $body['promptFeedback']['blockReason'] ?? null;

        if (is_string($blockReason) && $blockReason !== '') {
            throw $this->refused();
        }

        $candidate = $body['candidates'][0] ?? null;

        if (! is_array($candidate)) {
            throw $this->invalidResponse('Gemini no devolvió una generación.');
        }

        $finishReason = $candidate['finishReason'] ?? null;

        if (is_string($finishReason) && in_array($finishReason, self::REFUSAL_FINISH_REASONS, true)) {
            throw $this->refused();
        }

        if ($finishReason !== 'STOP') {
            throw $this->invalidResponse('Gemini no devolvió una generación completa.');
        }

        $text = $this->outputText($candidate);

        if ($text === '') {
            throw $this->invalidResponse('Gemini no devolvió el DashboardSpec esperado.');
        }

        try {
            $definition = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiGenerationException(
                'AI_INVALID_RESPONSE',
                'Gemini devolvió un DashboardSpec que no es JSON válido.',
                $exception,
            );
        }

        if (! is_array($definition) || array_is_list($definition)) {
            throw $this->invalidResponse('Gemini devolvió un DashboardSpec con una forma inválida.');
        }

        return ProviderSpecNormalizer::pruneDisplayToType(
            ProviderSpecNormalizer::dropNullMetricFormats($definition),
        );
    }

    private function assertSuccessfulStatus(Response $response): void
    {
        $status = $response->status();

        if ($response->successful()) {
            return;
        }

        if ($status === 401 || $status === 403 || ($status === 400 && $this->isInvalidApiKey($response))) {
            throw new AiGenerationException(
                'AI_AUTHENTICATION_FAILED',
                'Gemini rechazó la credencial configurada.',
            );
        }

        if ($status === 429) {
            throw new AiGenerationException(
                'AI_RATE_LIMITED',
                'Gemini alcanzó temporalmente su límite de solicitudes. Inténtalo más tarde.',
            );
        }

        if ($status === 404 || $response->serverError()) {
            throw new AiGenerationException(
                'AI_UNAVAILABLE',
                'Gemini no está disponible temporalmente.',
            );
        }

        throw $this->invalidResponse('Gemini rechazó la solicitud de generación.');
    }

    /**
     * Gemini reports a bad key as HTTP 400 with an API_KEY_INVALID reason.
     */
    private function isInvalidApiKey(Response $response): bool
    {
        $details = $response->json('error.details');

        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && ($detail['reason'] ?? null) === 'API_KEY_INVALID') {
                    return true;
                }
            }
        }

        $message = $response->json('error.message');

        return is_string($message) && stripos($message, 'API key not valid') !== false;
    }

    /**
     * Concatenates the answer parts, skipping any reasoning ("thought") parts.
     *
     * @param  array<string, mixed>  $candidate
     */
    private function outputText(array $candidate): string
    {
        $parts = $candidate['content']['parts'] ?? null;

        if (! is_array($parts)) {
            return '';
        }

        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && ($part['thought'] ?? false) !== true) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    /**
     * @return array<string, int>
     */
    private function usageFrom(Response $response): array
    {
        $usage = $response->json('usageMetadata');

        if (! is_array($usage)) {
            return [];
        }

        $map = [
            'promptTokenCount' => 'input_tokens',
            'candidatesTokenCount' => 'output_tokens',
            'thoughtsTokenCount' => 'thought_tokens',
            'totalTokenCount' => 'total_tokens',
        ];
        $counts = [];

        foreach ($map as $source => $target) {
            if (is_int($usage[$source] ?? null)) {
                $counts[$target] = $usage[$source];
            }
        }

        return $counts;
    }

    private function refused(): AiGenerationException
    {
        return new AiGenerationException(
            'AI_GENERATION_REFUSED',
            'El modelo no pudo generar una definición para esta solicitud.',
        );
    }

    private function invalidResponse(string $message): AiGenerationException
    {
        return new AiGenerationException('AI_INVALID_RESPONSE', $message);
    }

    private function connectionFailure(ConnectionException $exception): AiGenerationException
    {
        $isTimeout = TransientHttpFailures::isTimeout($exception);

        return new AiGenerationException(
            $isTimeout ? 'AI_TIMEOUT' : 'AI_UNAVAILABLE',
            $isTimeout
                ? 'Gemini tardó demasiado en responder.'
                : 'No fue posible conectar con Gemini.',
            $exception,
        );
    }
}
