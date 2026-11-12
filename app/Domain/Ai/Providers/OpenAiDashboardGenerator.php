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

final readonly class OpenAiDashboardGenerator implements DashboardGenerator, ModelAwareDashboardGenerator
{
    public function __construct(
        private string $apiKey,
        private string $configuredModel,
        private int $timeout,
        private int $maxRetries,
        private DashboardSpecSchema $schema,
        private DashboardGenerationInstructions $instructions,
    ) {}

    public function provider(): string
    {
        return 'openai';
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
            $result = 'OPENAI_UNAVAILABLE';

            throw new AiGenerationException(
                'OPENAI_UNAVAILABLE',
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
                'OPENAI_AUTHENTICATION_FAILED',
                'La integración con OpenAI no tiene una credencial configurada.',
            );
        }

        if (trim($this->configuredModel) === '') {
            throw new AiGenerationException(
                'OPENAI_UNAVAILABLE',
                'El modelo de generación no está configurado.',
            );
        }
    }

    private function send(DashboardGenerationInput $input): Response
    {
        try {
            return Http::baseUrl('https://api.openai.com/v1')
                ->acceptJson()
                ->asJson()
                ->withToken($this->apiKey)
                ->timeout(max(1, $this->timeout))
                ->retry(
                    max(1, $this->maxRetries + 1),
                    fn (int $attempt): int => TransientHttpFailures::backoffMilliseconds($attempt),
                    fn (Throwable $exception): bool => TransientHttpFailures::shouldRetry($exception),
                    throw: false,
                )
                ->post('/responses', $this->payload($input));
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
            'model' => $this->configuredModel,
            'store' => false,
            'input' => [
                [
                    'role' => 'system',
                    'content' => $this->instructions->systemFor($input),
                ],
                [
                    'role' => 'user',
                    'content' => $this->instructions->userMessageFor($input),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'insightflow_dashboard_spec_v1',
                    'description' => 'A validated InsightFlow DashboardSpec version 1.0.',
                    'strict' => true,
                    'schema' => $this->schema->forStructuredOutputs(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDefinition(Response $response): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiGenerationException(
                'OPENAI_AUTHENTICATION_FAILED',
                'OpenAI rechazó la credencial configurada.',
            );
        }

        if ($response->status() === 429) {
            throw new AiGenerationException(
                'OPENAI_RATE_LIMITED',
                'OpenAI alcanzó temporalmente su límite de solicitudes. Inténtalo más tarde.',
            );
        }

        if ($response->serverError()) {
            throw new AiGenerationException(
                'OPENAI_UNAVAILABLE',
                'OpenAI no está disponible temporalmente.',
            );
        }

        if (! $response->successful()) {
            throw new AiGenerationException(
                'OPENAI_INVALID_RESPONSE',
                'OpenAI devolvió una respuesta que no pudo procesarse.',
            );
        }

        $body = $response->json();

        if (! is_array($body) || ($body['status'] ?? null) !== 'completed' || ! isset($body['output']) || ! is_array($body['output'])) {
            throw new AiGenerationException(
                'OPENAI_INVALID_RESPONSE',
                'OpenAI no devolvió una generación completa.',
            );
        }

        $outputText = [];

        foreach ($body['output'] as $output) {
            if (! is_array($output) || ($output['type'] ?? null) !== 'message' || ! isset($output['content']) || ! is_array($output['content'])) {
                continue;
            }

            foreach ($output['content'] as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    throw new AiGenerationException(
                        'AI_GENERATION_REFUSED',
                        'El modelo no pudo generar una definición para esta solicitud.',
                    );
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $outputText[] = $content['text'];
                }
            }
        }

        if ($outputText === []) {
            throw new AiGenerationException(
                'OPENAI_INVALID_RESPONSE',
                'OpenAI no devolvió el DashboardSpec esperado.',
            );
        }

        try {
            $definition = json_decode(implode('', $outputText), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiGenerationException(
                'OPENAI_INVALID_RESPONSE',
                'OpenAI devolvió un DashboardSpec que no es JSON válido.',
                $exception,
            );
        }

        if (! is_array($definition) || array_is_list($definition)) {
            throw new AiGenerationException(
                'OPENAI_INVALID_RESPONSE',
                'OpenAI devolvió un DashboardSpec con una forma inválida.',
            );
        }

        return ProviderSpecNormalizer::dropNullMetricFormats($definition);
    }

    /**
     * @return array<string, int>
     */
    private function usageFrom(Response $response): array
    {
        $usage = $response->json('usage');

        if (! is_array($usage)) {
            return [];
        }

        return collect(['input_tokens', 'output_tokens', 'total_tokens'])
            ->filter(fn (string $key): bool => is_int($usage[$key] ?? null))
            ->mapWithKeys(fn (string $key): array => [$key => $usage[$key]])
            ->all();
    }

    private function connectionFailure(ConnectionException $exception): AiGenerationException
    {
        $isTimeout = TransientHttpFailures::isTimeout($exception);

        return new AiGenerationException(
            $isTimeout ? 'OPENAI_TIMEOUT' : 'OPENAI_UNAVAILABLE',
            $isTimeout
                ? 'OpenAI tardó demasiado en responder.'
                : 'No fue posible conectar con OpenAI.',
            $exception,
        );
    }
}
