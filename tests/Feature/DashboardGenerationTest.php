<?php

namespace Tests\Feature;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\OpenAiDashboardGenerator;
use App\Domain\Ai\Providers\UnsupportedDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_owner_can_open_dashboard_creation(): void
    {
        [$tenant, $user] = $this->ownerContext();

        $this->actingAs($user)
            ->get(route('dashboards.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboards/Create')
                ->where('currentTenant.slug', $tenant->slug)
                ->where('provider', 'fake'));
    }

    public function test_missing_driver_resolves_to_safe_unsupported_provider(): void
    {
        config(['dashboard-ai.driver' => null]);

        $this->assertInstanceOf(UnsupportedDashboardGenerator::class, app(DashboardGenerator::class));
    }

    public function test_openai_driver_and_model_are_selected_from_configuration(): void
    {
        config([
            'dashboard-ai.driver' => 'openai',
            'dashboard-ai.openai.api_key' => 'test-only-key',
            'dashboard-ai.openai.model' => 'gpt-configured-model',
        ]);

        $generator = app(DashboardGenerator::class);

        $this->assertInstanceOf(OpenAiDashboardGenerator::class, $generator);
        $this->assertSame('openai', $generator->provider());
        $this->assertSame('gpt-configured-model', $generator->model());

        [$tenant, $user] = $this->ownerContext();
        $this->actingAs($user)
            ->get(route('dashboards.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.slug', $tenant->slug)
                ->where('provider', 'openai')
                ->where('providerModel', 'gpt-configured-model'));
    }

    public function test_unknown_driver_never_falls_back_to_fake_or_openai(): void
    {
        config(['dashboard-ai.driver' => 'unknown-provider']);

        $this->assertInstanceOf(UnsupportedDashboardGenerator::class, app(DashboardGenerator::class));
    }

    public function test_user_can_queue_dashboard_and_generation_inside_current_tenant(): void
    {
        Queue::fake();
        [$tenant, $user] = $this->ownerContext();
        $prompt = 'Muéstrame las ventas por región del último trimestre.';

        $response = $this->actingAs($user)->post(route('dashboards.store'), [
            'prompt' => $prompt,
        ]);

        $dashboard = Dashboard::query()->sole();
        $generation = DashboardGeneration::query()->sole();

        $response->assertRedirect(route('dashboards.show', $dashboard));
        $this->assertSame($tenant->getKey(), $dashboard->tenant_id);
        $this->assertSame($user->getKey(), $dashboard->created_by);
        $this->assertSame(DashboardStatus::Queued, $dashboard->status);
        $this->assertSame($tenant->getKey(), $generation->tenant_id);
        $this->assertSame($dashboard->getKey(), $generation->dashboard_id);
        $this->assertSame(DashboardGenerationStatus::Queued, $generation->status);
        $this->assertSame($prompt, $generation->prompt);
        Queue::assertPushed(GenerateDashboardJob::class, fn (GenerateDashboardJob $job): bool => $job->generationId === $generation->getKey());
    }

    public function test_invalid_prompt_and_client_tenant_id_are_rejected(): void
    {
        Queue::fake();
        [$tenant, $user] = $this->ownerContext();
        $foreignTenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('dashboards.store'), [
            'prompt' => 'corto',
            'tenant_id' => $foreignTenant->getKey(),
        ])->assertSessionHasErrors(['prompt', 'tenant_id']);

        $this->assertDatabaseCount('dashboards', 0);
        $this->assertDatabaseCount('dashboard_generations', 0);
        $this->assertNotSame($tenant->getKey(), $foreignTenant->getKey());
    }

    public function test_generation_requests_are_rate_limited_per_user(): void
    {
        Queue::fake();
        config()->set('dashboard-ai.rate_limit.per_minute', 2);
        [, $user] = $this->ownerContext();
        [, $otherUser] = $this->ownerContext();
        $payload = ['prompt' => 'Muéstrame las ventas por región del último trimestre.'];

        $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertStatus(429);

        $this->assertDatabaseCount('dashboards', 2);

        $this->actingAs($otherUser)->post(route('dashboards.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('dashboards', 3);
    }

    public function test_the_global_daily_limit_is_shared_by_every_user(): void
    {
        Queue::fake();
        config()->set('dashboard-ai.rate_limit.global_per_day', 2);
        [, $user] = $this->ownerContext();
        [, $otherUser] = $this->ownerContext();
        $payload = ['prompt' => 'Muéstrame las ventas por región del último trimestre.'];

        $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertRedirect();
        $this->actingAs($otherUser)->post(route('dashboards.store'), $payload)->assertRedirect();

        // Both users are well inside their own limits, but the shared budget is spent.
        $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertStatus(429);
        $this->actingAs($otherUser)->post(route('dashboards.store'), $payload)->assertStatus(429);

        $this->assertDatabaseCount('dashboards', 2);
    }

    public function test_the_global_daily_limit_is_off_by_default(): void
    {
        Queue::fake();
        config()->set('dashboard-ai.rate_limit.per_minute', 50);
        config()->set('dashboard-ai.rate_limit.per_hour', 50);
        [, $user] = $this->ownerContext();
        $payload = ['prompt' => 'Muéstrame las ventas por región del último trimestre.'];

        $this->assertSame(0, config('dashboard-ai.rate_limit.global_per_day'));

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($user)->post(route('dashboards.store'), $payload)->assertRedirect();
        }
    }

    public function test_real_job_transitions_from_queued_through_generating_to_ready(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);
        $fake = new FakeDashboardGenerator;

        $observingGenerator = new class($fake, $dashboard->getKey()) implements DashboardGenerator
        {
            public bool $observedGenerating = false;

            public function __construct(
                private readonly FakeDashboardGenerator $fake,
                private readonly string $dashboardId,
            ) {}

            public function provider(): string
            {
                return 'fake';
            }

            public function generate(DashboardGenerationInput $input): DashboardSpec
            {
                $this->observedGenerating = Dashboard::query()->findOrFail($this->dashboardId)->status === DashboardStatus::Generating;

                return $this->fake->generate($input);
            }
        };

        (new GenerateDashboardJob($generation->getKey()))->handle($observingGenerator, new DashboardSpecValidator);

        $this->assertTrue($observingGenerator->observedGenerating);
        $this->assertSame(DashboardStatus::Ready, $dashboard->refresh()->status);
        $this->assertSame('1.1', $dashboard->spec_version);
        $this->assertNotNull($dashboard->dashboard_spec);
        $this->assertSame(DashboardGenerationStatus::Completed, $generation->refresh()->status);
        $this->assertSame($dashboard->dashboard_spec, $generation->validated_spec);
    }

    public function test_unsupported_provider_marks_dashboard_and_generation_failed(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user, 'unsupported');

        (new GenerateDashboardJob($generation->getKey()))->handle(new UnsupportedDashboardGenerator, new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertSame(DashboardGenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('UNSUPPORTED_PROVIDER', $generation->error_code);
        $this->assertNotNull($dashboard->error_message);
    }

    public function test_invalid_provider_response_is_rejected_and_not_persisted(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);

        $invalidGenerator = new class implements DashboardGenerator
        {
            public function provider(): string
            {
                return 'fake';
            }

            public function generate(DashboardGenerationInput $input): DashboardSpec
            {
                return DashboardSpec::fromArray(['version' => '1.0']);
            }
        };

        (new GenerateDashboardJob($generation->getKey()))->handle($invalidGenerator, new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertNull($dashboard->dashboard_spec);
        $this->assertSame('DASHBOARD_SPEC_INVALID', $generation->refresh()->error_code);
        $this->assertNull($generation->validated_spec);
    }

    public function test_job_is_idempotent_after_completion(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user);
        $fake = new FakeDashboardGenerator;

        $countingGenerator = new class($fake) implements DashboardGenerator
        {
            public int $calls = 0;

            public function __construct(private readonly FakeDashboardGenerator $fake) {}

            public function provider(): string
            {
                return 'fake';
            }

            public function generate(DashboardGenerationInput $input): DashboardSpec
            {
                $this->calls++;

                return $this->fake->generate($input);
            }
        };

        $job = new GenerateDashboardJob($generation->getKey());
        $job->handle($countingGenerator, new DashboardSpecValidator);
        $job->handle($countingGenerator, new DashboardSpecValidator);

        $this->assertSame(1, $countingGenerator->calls);
        $this->assertSame(DashboardStatus::Ready, $dashboard->refresh()->status);
    }

    public function test_openai_authentication_failure_is_persisted_safely_with_the_model_used(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user, 'openai');
        $secret = 'test-api-key-sensitive-value';
        config([
            'dashboard-ai.driver' => 'openai',
            'dashboard-ai.openai.api_key' => $secret,
            'dashboard-ai.openai.model' => 'gpt-test-model',
            'dashboard-ai.openai.max_retries' => 0,
        ]);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['error' => ['message' => "invalid {$secret}"]], 401),
        ]);

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertNull($dashboard->dashboard_spec);
        $this->assertSame(DashboardGenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('OPENAI_AUTHENTICATION_FAILED', $generation->error_code);
        $this->assertSame('gpt-test-model', $generation->model);
        $this->assertStringNotContainsString($secret, (string) $generation->error_message);
        $this->assertStringNotContainsString($secret, (string) $dashboard->error_message);

        $this->actingAs($user)
            ->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.generation.provider', 'openai')
                ->where('dashboard.generation.model', 'gpt-test-model'));
    }

    public function test_openai_output_still_passes_through_domain_catalog_validation(): void
    {
        [$tenant, $user] = $this->ownerContext();
        [$dashboard, $generation] = $this->queuedGeneration($tenant, $user, 'openai');
        config([
            'dashboard-ai.driver' => 'openai',
            'dashboard-ai.openai.api_key' => 'test-only-key',
            'dashboard-ai.openai.max_retries' => 0,
        ]);
        $definition = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($tenant->getKey(), (int) $user->getKey(), 'ventas por región'))
            ->toArray();
        $definition['visualizations'][0]['query']['metrics'][0]['field'] = 'invented_revenue';
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($definition, JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], 200),
        ]);

        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), new DashboardSpecValidator);

        $this->assertSame(DashboardStatus::Failed, $dashboard->refresh()->status);
        $this->assertSame('DASHBOARD_SPEC_INVALID', $generation->refresh()->error_code);
        $this->assertNull($generation->validated_spec);
    }

    public function test_user_cannot_view_or_modify_dashboard_from_another_tenant(): void
    {
        [$tenant, $user] = $this->ownerContext();
        $foreignTenant = Tenant::factory()->create();
        $foreignDashboard = Dashboard::factory()->for($foreignTenant)->create();

        $this->actingAs($user)
            ->get(route('dashboards.show', $foreignDashboard))
            ->assertForbidden();

        app(CurrentTenant::class)->set($tenant);
        $this->assertFalse(Gate::forUser($user)->allows('update', $foreignDashboard));
    }

    public function test_listing_and_tenant_switch_only_expose_current_tenant_dashboards(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $user = User::factory()->ownerOf($first)->memberOf($second)->create();
        Dashboard::factory()->for($first)->create(['title' => 'First tenant dashboard']);
        Dashboard::factory()->for($second)->create(['title' => 'Second tenant dashboard']);

        $this->actingAs($user)
            ->withSession([(string) config('tenancy.session_key') => $first->getKey()])
            ->get(route('dashboards.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboards', 1)
                ->where('dashboards.0.title', 'First tenant dashboard'));

        $this->patch(route('tenants.select', $second))->assertRedirect(route('dashboard'));

        $this->get(route('dashboards.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboards', 1)
                ->where('dashboards.0.title', 'Second tenant dashboard'));
    }

    public function test_transient_ready_and_failed_states_are_exposed_to_dashboard_detail(): void
    {
        [$tenant, $user] = $this->ownerContext();
        $statuses = [
            DashboardStatus::Queued,
            DashboardStatus::Generating,
            DashboardStatus::Ready,
            DashboardStatus::Failed,
        ];

        foreach ($statuses as $status) {
            $dashboard = Dashboard::factory()->for($tenant)->create([
                'created_by' => $user->getKey(),
                'status' => $status,
                'error_message' => $status === DashboardStatus::Failed ? 'Error seguro de prueba.' : null,
            ]);

            $this->actingAs($user)
                ->get(route('dashboards.show', $dashboard))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('dashboards/Show')
                    ->where('dashboard.status', $status->value));
        }
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
    private function queuedGeneration(Tenant $tenant, User $user, string $provider = 'fake'): array
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
            'status' => DashboardGenerationStatus::Queued,
            'prompt' => $dashboard->original_prompt,
        ]);

        return [$dashboard, $generation];
    }
}
