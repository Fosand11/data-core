<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Ai\Prompts\DashboardGenerationInstructions;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\OpenAiDashboardGenerator;
use App\Domain\Ai\Schemas\DashboardSpecSchema;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenAiDashboardGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_valid_structured_response_returns_a_domain_valid_spec_and_sends_only_approved_context(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response($this->completedResponse($this->validSpec()), 200),
        ]);

        $input = $this->input();
        $spec = $this->generator(model: 'gpt-test-model')->generate($input);

        $this->assertSame($spec, (new DashboardSpecValidator)->validate($spec));
        $this->assertSame('Ventas por región', $spec->toArray()['title']);

        Http::assertSent(function (Request $request) use ($input): bool {
            $payload = $request->data();
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            $systemInstructions = $payload['input'][0]['content'] ?? '';
            $schema = $payload['text']['format']['schema'] ?? [];

            $this->assertSame('POST', $request->method());
            $this->assertSame('https://api.openai.com/v1/responses', $request->url());
            $this->assertSame('gpt-test-model', $payload['model']);
            $this->assertFalse($payload['store']);
            $this->assertSame($input->prompt, $payload['input'][1]['content']);
            $this->assertTrue($payload['text']['format']['strict']);
            $this->assertSame('json_schema', $payload['text']['format']['type']);
            $this->assertArrayNotHasKey('tools', $payload);
            $this->assertStringNotContainsString($input->tenantId, $encodedPayload);
            $this->assertStringNotContainsString((string) $input->requestedBy, $encodedPayload);
            $this->assertStringContainsString('total_amount: numeric', $systemInstructions);
            $this->assertStringContainsString('salesperson: string', $systemInstructions);
            $this->assertStringNotContainsString('sales_representative', $systemInstructions);
            $this->assertArrayHasKey('anyOf', $schema['$defs']['visualization']);
            $this->assertArrayNotHasKey('allOf', $schema['$defs']['visualization']);
            $this->assertContains('format', $schema['$defs']['metric']['required']);
            $this->assertContains(null, $schema['$defs']['metric']['properties']['format']['enum'], true);

            return true;
        });
    }

    public function test_nullable_optional_metric_format_is_removed_before_domain_validation(): void
    {
        $definition = $this->validSpec();
        $definition['visualizations'][0]['query']['metrics'][0]['format'] = null;

        Http::fake([
            'api.openai.com/v1/responses' => Http::response($this->completedResponse($definition), 200),
        ]);

        $spec = $this->generator()->generate($this->input());

        $this->assertArrayNotHasKey('format', $spec->toArray()['visualizations'][0]['query']['metrics'][0]);
        $this->assertSame($spec, (new DashboardSpecValidator)->validate($spec));
    }

    public function test_missing_api_key_fails_before_any_http_request(): void
    {
        try {
            $this->generator(apiKey: '')->generate($this->input());
            $this->fail('Expected an authentication failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('OPENAI_AUTHENTICATION_FAILED', $exception->errorCode);
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    #[DataProvider('httpFailureProvider')]
    public function test_http_failures_are_classified_without_exposing_provider_payloads(int $status, string $expectedCode): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'error' => ['message' => 'provider detail that must remain internal'],
            ], $status),
        ]);

        try {
            $this->generator(maxRetries: 0)->generate($this->input());
            $this->fail('Expected a classified provider failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame($expectedCode, $exception->errorCode);
            $this->assertStringNotContainsString('provider detail', $exception->getMessage());
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function httpFailureProvider(): iterable
    {
        yield 'unauthorized' => [401, 'OPENAI_AUTHENTICATION_FAILED'];
        yield 'forbidden' => [403, 'OPENAI_AUTHENTICATION_FAILED'];
        yield 'rate limited' => [429, 'OPENAI_RATE_LIMITED'];
        yield 'server error' => [503, 'OPENAI_UNAVAILABLE'];
        yield 'unexpected client error' => [422, 'OPENAI_INVALID_RESPONSE'];
    }

    public function test_connection_timeout_is_classified(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectExceptionObject(new AiGenerationException(
            'OPENAI_TIMEOUT',
            'OpenAI tardó demasiado en responder.',
        ));

        $this->generator(maxRetries: 0)->generate($this->input());
    }

    public function test_refusal_is_classified_without_persisting_refusal_text(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'refusal',
                        'refusal' => 'Sensitive refusal detail.',
                    ]],
                ]],
            ], 200),
        ]);

        try {
            $this->generator()->generate($this->input());
            $this->fail('Expected a refusal.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_GENERATION_REFUSED', $exception->errorCode);
            $this->assertStringNotContainsString('Sensitive refusal detail', $exception->getMessage());
        }
    }

    public function test_malformed_or_incomplete_success_responses_are_rejected(): void
    {
        Http::fakeSequence()
            ->push([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => '{not-json']],
                ]],
            ], 200)
            ->push(['status' => 'incomplete', 'output' => []], 200);

        foreach (range(1, 2) as $_) {
            try {
                $this->generator()->generate($this->input());
                $this->fail('Expected an invalid response.');
            } catch (AiGenerationException $exception) {
                $this->assertSame('OPENAI_INVALID_RESPONSE', $exception->errorCode);
            }
        }
    }

    public function test_transient_server_failure_is_retried_then_succeeds(): void
    {
        Http::fakeSequence()
            ->pushStatus(503)
            ->push($this->completedResponse($this->validSpec()), 200);

        $spec = $this->generator(maxRetries: 1)->generate($this->input());

        $this->assertSame('Ventas por región', $spec->toArray()['title']);
        Http::assertSentCount(2);
    }

    public function test_usage_is_logged_as_counts_without_prompt_or_secret(): void
    {
        Log::spy();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response($this->completedResponse($this->validSpec(), [
                'input_tokens' => 101,
                'output_tokens' => 202,
                'total_tokens' => 303,
            ]), 200),
        ]);

        $input = $this->input();
        $this->generator()->generate($input);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($input): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'Dashboard AI provider request finished.'
                && $context['provider'] === 'openai'
                && $context['model'] === 'gpt-5.6-terra'
                && $context['input_tokens'] === 101
                && $context['output_tokens'] === 202
                && $context['total_tokens'] === 303
                && ! str_contains($encoded, $input->prompt)
                && ! str_contains($encoded, 'test-secret');
        });
    }

    private function generator(
        string $apiKey = 'test-secret',
        string $model = 'gpt-5.6-terra',
        int $maxRetries = 0,
    ): OpenAiDashboardGenerator {
        $catalog = new DemoSalesDatasetCatalog;

        return new OpenAiDashboardGenerator(
            apiKey: $apiKey,
            configuredModel: $model,
            timeout: 60,
            maxRetries: $maxRetries,
            schema: new DashboardSpecSchema,
            instructions: new DashboardGenerationInstructions($catalog),
        );
    }

    private function input(): DashboardGenerationInput
    {
        return new DashboardGenerationInput(
            tenantId: '0198-tenant-internal-id',
            requestedBy: 987654321,
            prompt: 'Quiero ver las ventas por región del último trimestre.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validSpec(): array
    {
        return (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput('internal', 1, 'ventas por región'))
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, int>  $usage
     * @return array<string, mixed>
     */
    private function completedResponse(array $definition, array $usage = []): array
    {
        return [
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($definition, JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => $usage,
        ];
    }
}
