<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationKind;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DashboardVersion;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * The guarantees that keep one organization's data away from another's, tested at
 * every layer: the model scope, route binding, queued jobs and the schema itself.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
    }

    // --- the model scope --------------------------------------------------------

    public function test_with_no_tenant_in_context_nothing_is_visible_not_everything(): void
    {
        $this->ownedBy($this->a);
        $this->ownedBy($this->b);
        app(CurrentTenant::class)->forget();

        foreach ([Dashboard::class, DashboardGeneration::class, DashboardVersion::class, DemoSale::class] as $model) {
            $this->assertSame(0, $model::query()->count(), "{$model} leaked without a tenant in context");
            $this->assertSame(2, $model::withoutTenantScope()->count(), "{$model} should exist in two tenants");
        }
    }

    public function test_every_tenant_owned_model_only_sees_the_current_tenant(): void
    {
        $a = $this->ownedBy($this->a);
        $b = $this->ownedBy($this->b);

        foreach ([[$this->a, $a, $b], [$this->b, $b, $a]] as [$tenant, $mine, $theirs]) {
            app(TenantContext::class)->run($tenant, function () use ($tenant, $mine, $theirs): void {
                foreach ($mine as $record) {
                    $this->assertNotNull($record::query()->find($record->getKey()), 'own record must be visible');
                }

                foreach ($theirs as $record) {
                    $this->assertNull($record::query()->find($record->getKey()), 'foreign record must be invisible');
                    $this->assertFalse($record::query()->whereKey($record->getKey())->exists());
                }

                $this->assertSame(1, Dashboard::query()->count());
                $this->assertSame($tenant->getKey(), Dashboard::query()->sole()->tenant_id);
            });
        }
    }

    public function test_a_record_cannot_be_created_for_another_tenant_while_one_is_in_context(): void
    {
        app(CurrentTenant::class)->set($this->a);

        $this->expectException(LogicException::class);

        Dashboard::factory()->for($this->b)->create();
    }

    public function test_a_record_can_never_change_owner(): void
    {
        $dashboard = app(TenantContext::class)->run($this->a, fn (): Dashboard => Dashboard::factory()->for($this->a)->create());

        $this->expectException(LogicException::class);

        app(TenantContext::class)->run($this->a, function () use ($dashboard): void {
            $dashboard->tenant_id = $this->b->getKey();
            $dashboard->save();
        });
    }

    public function test_deleting_and_updating_cannot_reach_another_tenants_rows(): void
    {
        $b = $this->ownedBy($this->b);

        app(TenantContext::class)->run($this->a, function (): void {
            $this->assertSame(0, Dashboard::query()->update(['title' => 'hijacked']));
            $this->assertSame(0, Dashboard::query()->delete());
            $this->assertSame(0, DemoSale::query()->delete());
        });

        $this->assertNotSame('hijacked', $b[0]->refresh()->title);
        $this->assertSame(1, DemoSale::withoutTenantScope()->forTenant($this->b)->count());
    }

    // --- HTTP ---------------------------------------------------------------------

    public function test_a_foreign_dashboard_is_indistinguishable_from_one_that_does_not_exist(): void
    {
        $owner = User::factory()->ownerOf($this->a)->create();
        [$foreign] = $this->ownedBy($this->b);

        $foreignResponse = $this->actingAs($owner)->get(route('dashboards.show', $foreign));
        $missingResponse = $this->actingAs($owner)->get(route('dashboards.show', Str::uuid()->toString()));

        $foreignResponse->assertNotFound();
        $missingResponse->assertNotFound();
        $this->assertSame($missingResponse->getStatusCode(), $foreignResponse->getStatusCode());
        $this->assertSame($missingResponse->getContent(), $foreignResponse->getContent());
    }

    public function test_every_route_with_a_dashboard_rejects_another_tenants_dashboard(): void
    {
        $owner = User::factory()->ownerOf($this->a)->create();
        [$foreign, $foreignGeneration] = $this->ownedBy($this->b);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), '{dashboard}'));

        // The sweep must not pass vacuously, and it grows with the application.
        $this->assertGreaterThanOrEqual(10, $routes->count());

        $leaks = [];

        foreach ($routes as $route) {
            $uri = str_replace(
                ['{dashboard}', '{refinement}', '{number}'],
                [$foreign->getKey(), $foreignGeneration->getKey(), '1'],
                $route->uri(),
            );
            $method = collect($route->methods())->first(fn (string $m): bool => ! in_array($m, ['HEAD', 'OPTIONS'], true));

            $status = $this->actingAs($owner)->call($method, '/'.$uri, ['instruction' => 'hijack', 'pinned' => true, 'title' => 'hijack'])->getStatusCode();

            if ($status !== 404) {
                $leaks[] = "{$method} /{$route->uri()} answered {$status}";
            }
        }

        $this->assertSame([], $leaks, 'Routes that do not treat a foreign dashboard as not found');
        $this->assertNotSame('hijack', $foreign->refresh()->title);
    }

    public function test_a_member_of_two_tenants_only_ever_sees_the_selected_one(): void
    {
        [$mineA] = $this->ownedBy($this->a);
        [$mineB] = $this->ownedBy($this->b);
        $user = User::factory()->ownerOf($this->a)->create();
        $this->b->attachUser($user, TenantRole::Owner);

        $this->actingAs($user)->patch(route('tenants.select', $this->a->slug))->assertRedirect();
        $this->actingAs($user)->get(route('dashboards.show', $mineA))->assertOk();
        $this->actingAs($user)->get(route('dashboards.show', $mineB))->assertNotFound();

        $this->actingAs($user)->patch(route('tenants.select', $this->b->slug))->assertRedirect();
        $this->actingAs($user)->get(route('dashboards.show', $mineB))->assertOk();
        $this->actingAs($user)->get(route('dashboards.show', $mineA))->assertNotFound();
    }

    public function test_a_user_cannot_select_an_organization_they_do_not_belong_to(): void
    {
        $user = User::factory()->ownerOf($this->a)->create();

        $this->actingAs($user)->patch(route('tenants.select', $this->b->slug))->assertForbidden();
        $this->actingAs($user)->get(route('dashboards.index'))->assertOk();
    }

    // --- queued work ---------------------------------------------------------------

    public function test_a_queued_job_works_inside_its_own_tenant_whatever_was_in_context(): void
    {
        Queue::fake();
        config(['dashboard-ai.driver' => 'fake']);
        $owner = User::factory()->ownerOf($this->b)->create();

        $this->actingAs($owner)->post(route('dashboards.store'), ['prompt' => 'Muéstrame las ventas por región del último trimestre.'])->assertRedirect();
        $generation = DashboardGeneration::withoutTenantScope()->sole();

        // A worker that handled tenant A's job a moment ago must not carry A into B's job.
        app(CurrentTenant::class)->set($this->a);
        (new GenerateDashboardJob($generation->getKey()))->handle(app(DashboardGenerator::class), app(DashboardSpecValidator::class));

        $dashboard = Dashboard::withoutTenantScope()->findOrFail($generation->dashboard_id);

        $this->assertSame($this->b->getKey(), $dashboard->tenant_id);
        $this->assertSame(DashboardStatus::Ready, $dashboard->status);
        $this->assertSame($this->b->getKey(), app(CurrentTenant::class)->id());
        $this->assertSame(0, Dashboard::withoutTenantScope()->forTenant($this->a)->count());
    }

    // --- the data itself -------------------------------------------------------------

    public function test_each_tenant_sums_only_its_own_rows(): void
    {
        app(TenantContext::class)->run($this->a, function (): void {
            DemoSale::factory()->for($this->a)->create(['region' => 'Norte', 'total_amount' => 100]);
            DemoSale::factory()->for($this->a)->create(['region' => 'Norte', 'total_amount' => 50]);
        });
        app(TenantContext::class)->run($this->b, function (): void {
            DemoSale::factory()->for($this->b)->create(['region' => 'Norte', 'total_amount' => 9000]);
        });

        $sum = fn (Tenant $tenant): float => (float) app(TenantContext::class)->run($tenant, fn () => DemoSale::query()->sum('total_amount'));

        $this->assertSame(150.0, $sum($this->a));
        $this->assertSame(9000.0, $sum($this->b));
    }

    // --- structure ---------------------------------------------------------------------

    public function test_every_table_with_a_tenant_id_has_a_foreign_key_to_tenants_and_an_index(): void
    {
        $checked = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (! Schema::hasColumn($name, 'tenant_id')) {
                continue;
            }

            $checked[] = $name;
            $foreignKeys = collect(Schema::getForeignKeys($name))
                ->filter(fn (array $fk): bool => $fk['columns'] === ['tenant_id'] && $fk['foreign_table'] === 'tenants');
            $indexed = collect(Schema::getIndexes($name))
                ->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'tenant_id');

            $this->assertCount(1, $foreignKeys, "{$name}.tenant_id needs a foreign key to tenants");
            $this->assertTrue($indexed, "{$name}.tenant_id needs an index (its own, or the first column of one)");
        }

        $this->assertContains('dashboards', $checked);
        $this->assertContains('demo_sales', $checked);
    }

    public function test_every_model_with_a_tenant_id_uses_the_tenant_scope(): void
    {
        $checked = 0;

        foreach (glob(app_path('Domain/*/Models/*.php')) as $file) {
            $class = 'App\\Domain\\'.basename(dirname(dirname($file))).'\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! in_array('tenant_id', (new $class)->getFillable(), true)) {
                continue;
            }

            $checked++;
            $this->assertContains(BelongsToTenant::class, class_uses_recursive($class), "{$class} has a tenant_id but is not tenant-scoped");
        }

        $this->assertGreaterThanOrEqual(4, $checked);
    }

    // --- helpers -------------------------------------------------------------------------

    /**
     * One of each tenant-owned record, created as that tenant.
     *
     * @return array{0: Dashboard, 1: DashboardGeneration, 2: DashboardVersion, 3: DemoSale}
     */
    private function ownedBy(Tenant $tenant): array
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant): array {
            $dashboard = Dashboard::factory()->for($tenant)->create(['status' => DashboardStatus::Ready, 'dashboard_spec' => ['version' => '1.1']]);
            $generation = DashboardGeneration::factory()->create([
                'dashboard_id' => $dashboard->getKey(),
                'tenant_id' => $tenant->getKey(),
                'kind' => DashboardGenerationKind::Refine,
                'status' => DashboardGenerationStatus::Completed,
                'validated_spec' => ['version' => '1.1'],
            ]);
            $version = $dashboard->versions()->create([
                'tenant_id' => $tenant->getKey(),
                'number' => 1,
                'source' => DashboardVersion::SOURCE_GENERATED,
                'instruction' => null,
                'spec_version' => '1.1',
                'spec' => ['version' => '1.1'],
                'created_by' => null,
            ]);
            $sale = DemoSale::factory()->for($tenant)->create();

            return [$dashboard, $generation, $version, $sale];
        });
    }
}
