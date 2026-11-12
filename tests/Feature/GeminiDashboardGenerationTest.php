<?php

namespace Tests\Feature;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\GeminiDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GeminiDashboardGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'dashboard-ai.driver' => 'gemini',
            'dashboard-ai.gemini.api_key' => 'test-only-gemini-key',
            'dashboard-ai.gemini.model' => 'gemini-test-model',
            'dashboard-ai.gemini.max_retries' => 0,
        ]);
    }

    public function test_gemini_driver_and_model_are_selected_from_configuration(): void
    {
        $generator = app(DashboardGenerator::class);

        $this->assertInstanceOf(GeminiDashboardGenerator::class, $generator);
        $this->assertSame('gemini', $generator->provider());
        $this->assertSame('gemini-test-model', $generator->model());

        [$tenant, $user] = $this->ownerContext();
        $this->actingAs($user)
            ->get(route('dashboards.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.slug', $tenant->slug)
                ->where('provider', 'gemini')
                ->where('providerModel', 'gemini-test-model'));
    }

    public function test_driver_names_are_case_and_whitespace_insensitive(): void
    {
        config(['dashboard-ai.driver' => '  Gemini ']);

        $this->assertInstanceOf(GeminiDashboardGenerator::class, app(DashboardGenerator::class));
    }

    public function test_a_gemini_generation_completes_end_to_end_and_the_dashboard_shows_real_data(): void
    {
        [$tenant, $user] = $this->ownerContext();
        DemoSale::factory()->for($tenant)->create(['region' => 'Norte', 'total_amount' => 250, 'sold_at' => now()->subMonths(4)]);
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);
        $spec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($tenant->getKey(), $user->getKey(), $generation->prompt))
            ->toArray();
        // The pie/bar/KPIs of the fake spec carry a global previous-quarter filter; drop it so the sale is counted.
        $spec['global_filters'] = [];

        Http::fake([self::ENDPOINT => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['role' => 'model', 'parts' => [['text' => json_encode($spec, JSON_THROW_ON_ERROR)]]],
            ]],
            'usageMetadata' => ['promptTokenCount' => 900, 'candidatesTokenCount' => 600, 'totalTokenCount' => 1500],
        ], 200)]);

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Ready, $dashboard->refresh()->status);
        $this->assertSame('1.1', $dashboard->spec_version);
        $this->assertSame(DashboardGenerationStatus::Completed, $generation->refresh()->status);
        $this->assertSame('gemini-test-model', $generation->model);

        $this->actingAs($user)
            ->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.generation.provider', 'gemini')
                ->where('dashboard.generation.model', 'gemini-test-model')
                ->where('visualizationData.total_sales.rows.0.total_sales', 250));
    }

    public function test_an_unsafe_gemini_spec_is_rejected_and_never_persisted(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);
        $spec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($tenant->getKey(), $user->getKey(), $generation->prompt))
            ->toArray();
        $spec['description'] = 'SELECT * FROM users WHERE 1=1';

        Http::fake([self::ENDPOINT => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => json_encode($spec, JSON_THROW_ON_ERROR)]]],
            ]],
        ], 200)]);

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertNull($dashboard->dashboard_spec);
        $this->assertSame('DASHBOARD_SPEC_INVALID', $generation->refresh()->error_code);
        $this->assertNull($generation->validated_spec);
    }

    public function test_gemini_rate_limit_is_persisted_with_a_safe_message(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);

        Http::fake([self::ENDPOINT => Http::response([
            'error' => ['code' => 429, 'message' => 'Quota exceeded for metric ... internal detail', 'status' => 'RESOURCE_EXHAUSTED'],
        ], 429)]);

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertSame('AI_RATE_LIMITED', $generation->refresh()->error_code);
        $this->assertSame('gemini-test-model', $generation->model);
        $this->assertStringNotContainsString('Quota exceeded', (string) $generation->error_message);
        $this->assertStringNotContainsString('internal detail', (string) $dashboard->error_message);
        $this->assertStringContainsString('límite', (string) $generation->error_message);
    }

    public function test_a_queued_generation_for_another_provider_is_not_run_by_gemini(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user, provider: 'openai');

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        Http::assertNothingSent();
        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertSame('UNSUPPORTED_PROVIDER', $generation->refresh()->error_code);
    }

    /**
     * @return array{Tenant, User}
     */
    private function ownerContext(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();

        return [$tenant, $user];
    }

    /**
     * @return array{Dashboard, DashboardGeneration}
     */
    private function queuedGeneration(Tenant $tenant, User $user, string $provider = 'gemini'): array
    {
        $dashboard = Dashboard::factory()->for($tenant)->queued()->create([
            'created_by' => $user->getKey(),
            'provider' => $provider,
            'original_prompt' => 'Muéstrame las ventas por región del último trimestre.',
        ]);
        $generation = DashboardGeneration::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'dashboard_id' => $dashboard->getKey(),
            'requested_by' => $user->getKey(),
            'provider' => $provider,
            'prompt' => 'Muéstrame las ventas por región del último trimestre.',
        ]);

        return [$dashboard, $generation];
    }
}
