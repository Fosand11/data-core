<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Prompts\DashboardGenerationInstructions;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\GeminiDashboardGenerator;
use App\Domain\Ai\Schemas\DashboardSpecSchema;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiDashboardGeneratorTest extends TestCase
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_valid_response_returns_a_domain_valid_spec_and_sends_only_approved_context(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($this->validSpec()), 200)]);

        $input = $this->input();
        $spec = $this->generator()->generate($input);

        $this->assertSame($spec, (new DashboardSpecValidator)->validate($spec));
        $this->assertSame('Ventas por región', $spec->toArray()['title']);

        Http::assertSent(function (Request $request) use ($input): bool {
            $payload = $request->data();
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $schema = $payload['generationConfig']['responseJsonSchema'];

            $this->assertSame('POST', $request->method());
            $this->assertSame(self::ENDPOINT, $request->url());
            $this->assertSame(['test-secret'], $request->header('x-goog-api-key'));
            $this->assertStringNotContainsString('test-secret', $request->url());
            $this->assertStringNotContainsString('key=', $request->url());
            $this->assertSame($input->prompt, $payload['contents'][0]['parts'][0]['text']);
            $this->assertSame('user', $payload['contents'][0]['role']);
            $this->assertStringContainsString('total_amount: numeric', $payload['systemInstruction']['parts'][0]['text']);
            $this->assertSame('application/json', $payload['generationConfig']['responseMimeType']);
            $this->assertSame(1234, $payload['generationConfig']['maxOutputTokens']);
            $this->assertArrayNotHasKey('temperature', $payload['generationConfig']);
            $this->assertArrayNotHasKey('tools', $payload);
            $this->assertStringNotContainsString($input->tenantId, $encoded);
            $this->assertStringNotContainsString((string) $input->requestedBy, $encoded);
            $this->assertSame(['1.1'], $schema['properties']['version']['enum']);

            return true;
        });
    }

    public function test_a_refinement_sends_the_current_spec_and_the_change_but_no_internal_ids(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($this->validSpec()), 200)]);
        $current = $this->validSpec();
        $input = new DashboardGenerationInput('0198-tenant-internal-id', 987654321, 'cambia las barras por un gráfico circular', $current);

        $this->generator()->generate($input);

        Http::assertSent(function (Request $request) use ($input, $current): bool {
            $payload = $request->data();
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $system = $payload['systemInstruction']['parts'][0]['text'];
            $user = $payload['contents'][0]['parts'][0]['text'];

            $this->assertStringContainsString('Refinement mode', $system);
            $this->assertStringContainsString('total_amount: numeric', $system);
            $this->assertStringContainsString('CURRENT_DASHBOARD_SPEC', $user);
            $this->assertStringContainsString('REQUESTED_CHANGE:'."\n".$input->prompt, $user);
            $this->assertStringContainsString($current['title'], $user);
            $this->assertStringNotContainsString($input->tenantId, $encoded);
            $this->assertStringNotContainsString((string) $input->requestedBy, $encoded);
            $this->assertStringNotContainsString('test-secret', $encoded);

            return true;
        });
    }

    public function test_reasoning_parts_are_ignored_and_answer_parts_are_joined(): void
    {
        $json = json_encode($this->validSpec(), JSON_THROW_ON_ERROR);
        $half = intdiv(strlen($json), 2);

        Http::fake([self::ENDPOINT => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['role' => 'model', 'parts' => [
                    ['text' => 'internal reasoning that must be dropped', 'thought' => true],
                    ['text' => substr($json, 0, $half)],
                    ['text' => substr($json, $half)],
                ]],
            ]],
        ], 200)]);

        $spec = $this->generator()->generate($this->input());

        $this->assertSame('Ventas por región', $spec->toArray()['title']);
    }

    public function test_a_flat_display_is_pruned_to_the_keys_of_each_visualization_type(): void
    {
        $definition = $this->validSpec();
        // A single flat schema lets the model fill keys that belong to other types.
        $definition['visualizations'][0]['display']['columns'] = ['region'];
        $definition['visualizations'][0]['display']['x_axis'] = 'region';
        $definition['visualizations'][3]['display']['show_comparison'] = false;
        $definition['visualizations'][4]['display']['x_axis'] = 'region';

        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($definition), 200)]);

        $spec = $this->generator()->generate($this->input());
        $result = $spec->toArray();

        $this->assertSame(['show_comparison' => false], $result['visualizations'][0]['display']);
        $this->assertSame(['x_axis', 'y_axis'], array_keys($result['visualizations'][3]['display']));
        $this->assertSame(['category', 'value'], array_keys($result['visualizations'][4]['display']));
        $this->assertSame($spec, (new DashboardSpecValidator)->validate($spec));
    }

    public function test_null_metric_formats_are_removed_before_domain_validation(): void
    {
        $definition = $this->validSpec();
        $definition['visualizations'][0]['query']['metrics'][0]['format'] = null;

        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($definition), 200)]);

        $spec = $this->generator()->generate($this->input());

        $this->assertArrayNotHasKey('format', $spec->toArray()['visualizations'][0]['query']['metrics'][0]);
    }

    public function test_the_domain_validator_still_rejects_a_well_formed_but_unsafe_spec(): void
    {
        $definition = $this->validSpec();
        $definition['visualizations'][0]['query']['metrics'][0]['field'] = 'tenant_id';

        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($definition), 200)]);

        $spec = $this->generator()->generate($this->input());

        $this->expectException(InvalidDashboardSpecException::class);
        (new DashboardSpecValidator)->validate($spec);
    }

    public function test_missing_api_key_fails_before_any_http_request(): void
    {
        try {
            $this->generator(apiKey: '')->generate($this->input());
            $this->fail('Expected an authentication failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_AUTHENTICATION_FAILED', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    #[DataProvider('unsafeModelNames')]
    public function test_the_model_must_be_a_plain_model_code_before_it_reaches_the_url(string $model): void
    {
        try {
            $this->generator(model: $model)->generate($this->input());
            $this->fail('Expected a configuration failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_UNAVAILABLE', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeModelNames(): iterable
    {
        yield 'empty' => [''];
        yield 'path traversal' => ['../v1/models/other'];
        yield 'query string' => ['gemini-test?key=x'];
        yield 'colon action' => ['gemini-test:streamGenerateContent'];
        yield 'whitespace' => ['gemini test'];
    }

    #[DataProvider('httpFailureProvider')]
    public function test_http_failures_are_classified_without_exposing_provider_payloads(int $status, string $expectedCode): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'error' => ['code' => $status, 'message' => 'provider detail that must remain internal', 'status' => 'X'],
        ], $status)]);

        try {
            $this->generator()->generate($this->input());
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
        yield 'unauthorized' => [401, 'AI_AUTHENTICATION_FAILED'];
        yield 'forbidden' => [403, 'AI_AUTHENTICATION_FAILED'];
        yield 'rate limited' => [429, 'AI_RATE_LIMITED'];
        yield 'model not found' => [404, 'AI_UNAVAILABLE'];
        yield 'server error' => [500, 'AI_UNAVAILABLE'];
        yield 'service unavailable' => [503, 'AI_UNAVAILABLE'];
        yield 'schema or request rejected' => [400, 'AI_INVALID_RESPONSE'];
    }

    public function test_an_invalid_api_key_reported_as_400_is_an_authentication_failure(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'error' => [
                'code' => 400,
                'message' => 'API key not valid. Please pass a valid API key.',
                'status' => 'INVALID_ARGUMENT',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']],
            ],
        ], 400)]);

        try {
            $this->generator()->generate($this->input());
            $this->fail('Expected an authentication failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_AUTHENTICATION_FAILED', $exception->errorCode);
        }
    }

    public function test_rate_limits_are_not_retried_but_server_errors_are(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => ['message' => 'quota']], 429)
            ->push($this->completedResponse($this->validSpec()), 200)]);

        try {
            $this->generator(maxRetries: 2)->generate($this->input());
            $this->fail('Expected a rate limit failure.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_RATE_LIMITED', $exception->errorCode);
        }

        Http::assertSentCount(1);
    }

    public function test_transient_server_failure_is_retried_then_succeeds(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => ['message' => 'overloaded']], 503)
            ->push($this->completedResponse($this->validSpec()), 200)]);

        $spec = $this->generator(maxRetries: 2)->generate($this->input());

        $this->assertSame('Ventas por región', $spec->toArray()['title']);
        Http::assertSentCount(2);
    }

    public function test_connection_timeout_is_classified(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            $this->generator()->generate($this->input());
            $this->fail('Expected a timeout.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_TIMEOUT', $exception->errorCode);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('refusalProvider')]
    public function test_blocked_or_declined_generations_are_classified_as_refusals(array $body): void
    {
        Http::fake([self::ENDPOINT => Http::response($body, 200)]);

        try {
            $this->generator()->generate($this->input());
            $this->fail('Expected a refusal.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_GENERATION_REFUSED', $exception->errorCode);
            $this->assertStringNotContainsString('blocked content text', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusalProvider(): iterable
    {
        yield 'prompt blocked' => [['promptFeedback' => ['blockReason' => 'SAFETY']]];
        yield 'safety finish' => [['candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => [['text' => 'blocked content text']]]]]]];
        yield 'prohibited content' => [['candidates' => [['finishReason' => 'PROHIBITED_CONTENT']]]];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('malformedProvider')]
    public function test_malformed_or_incomplete_success_responses_are_rejected(array $body): void
    {
        Http::fake([self::ENDPOINT => Http::response($body, 200)]);

        try {
            $this->generator()->generate($this->input());
            $this->fail('Expected an invalid response.');
        } catch (AiGenerationException $exception) {
            $this->assertSame('AI_INVALID_RESPONSE', $exception->errorCode);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'no candidates' => [['candidates' => []]];
        yield 'empty body' => [[]];
        yield 'truncated by token limit' => [['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"version":"1.1"']]]]]]];
        yield 'missing finish reason' => [['candidates' => [['content' => ['parts' => [['text' => '{}']]]]]]];
        yield 'no text' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => []]]]]];
        yield 'invalid json' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'not json']]]]]]];
        yield 'json list' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '[1,2]']]]]]]];
    }

    public function test_usage_is_logged_as_counts_without_prompt_or_secret(): void
    {
        Log::spy();
        Http::fake([self::ENDPOINT => Http::response($this->completedResponse($this->validSpec(), [
            'promptTokenCount' => 1200,
            'candidatesTokenCount' => 800,
            'thoughtsTokenCount' => 300,
            'totalTokenCount' => 2300,
        ]), 200)]);

        $input = $this->input();
        $this->generator()->generate($input);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($input): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $context['provider'] === 'gemini'
                && $context['model'] === 'gemini-test-model'
                && $context['result'] === 'completed'
                && $context['input_tokens'] === 1200
                && $context['output_tokens'] === 800
                && $context['thought_tokens'] === 300
                && $context['total_tokens'] === 2300
                && ! str_contains($encoded, 'test-secret')
                && ! str_contains($encoded, $input->prompt);
        });
    }

    public function test_provider_and_model_are_reported_for_auditing(): void
    {
        $generator = $this->generator(model: 'gemini-test-model');

        $this->assertSame('gemini', $generator->provider());
        $this->assertSame('gemini-test-model', $generator->model());
    }

    private function generator(string $apiKey = 'test-secret', string $model = 'gemini-test-model', int $maxRetries = 0): GeminiDashboardGenerator
    {
        return new GeminiDashboardGenerator(
            apiKey: $apiKey,
            configuredModel: $model,
            timeout: 60,
            maxRetries: $maxRetries,
            maxOutputTokens: 1234,
            schema: new DashboardSpecSchema,
            instructions: new DashboardGenerationInstructions(new DemoSalesDatasetCatalog),
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
     * The fake provider's "ventas por región" dashboard: 3 KPIs, a bar chart and a pie.
     *
     * @return array<string, mixed>
     */
    private function validSpec(): array
    {
        return (new FakeDashboardGenerator)
            ->generate($this->input())
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
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [['text' => json_encode($definition, JSON_THROW_ON_ERROR)]],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => $usage,
        ];
    }
}
