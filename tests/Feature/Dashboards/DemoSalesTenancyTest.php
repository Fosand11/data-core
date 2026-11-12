<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Dashboards\Actions\SeedDemoSales;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoSalesTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_tenant_scoped_realistic_data_inside_the_last_year(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        $inserted = app(SeedDemoSales::class)->execute($tenant, 250);

        $this->assertSame(250, $inserted);
        $this->assertSame(250, DemoSale::query()->forTenant($tenant)->count());
        $this->assertSame(0, DemoSale::query()->forTenant($other)->count());
        $this->assertSame(0, DemoSale::query()->forTenant($tenant)->where('sold_at', '>', now())->count());
        $this->assertSame(0, DemoSale::query()->forTenant($tenant)->where('sold_at', '<', now()->subDays(367))->count());
        $this->assertSame(4, DemoSale::query()->forTenant($tenant)->distinct()->count('region'));
    }

    public function test_seeding_is_deterministic_per_tenant_and_differs_between_tenants(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $seed = app(SeedDemoSales::class);

        $fingerprint = fn (Tenant $tenant): array => DemoSale::query()
            ->forTenant($tenant)
            ->orderBy('product_id')->orderBy('total_amount')->orderBy('region')->orderBy('salesperson')
            ->get(['product_id', 'total_amount', 'region', 'salesperson'])
            ->map(fn (DemoSale $sale): string => "{$sale->product_id}|{$sale->total_amount}|{$sale->region}|{$sale->salesperson}")
            ->all();

        $seed->execute($first, 120);
        $firstRun = $fingerprint($first);
        DemoSale::query()->forTenant($first)->delete();
        $seed->execute($first, 120);
        $seed->execute($second, 120);

        $this->assertSame($firstRun, $fingerprint($first));
        $this->assertNotSame($firstRun, $fingerprint($second));
    }

    public function test_registration_seeds_demo_data_only_when_enabled(): void
    {
        config()->set('tenancy.seed_demo_data', true);

        $this->post('/register', [
            'name' => 'Seeded User',
            'email' => 'seeded@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertSame(600, DemoSale::query()->count());
        $this->assertSame(1, DemoSale::query()->distinct()->count('tenant_id'));
    }

    public function test_registration_does_not_seed_when_disabled(): void
    {
        config()->set('tenancy.seed_demo_data', false);

        $this->post('/register', [
            'name' => 'Empty User',
            'email' => 'empty@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertSame(0, DemoSale::query()->count());
    }

    public function test_policy_allows_current_tenant_members_and_rejects_foreign_sales(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = User::factory()->memberOf($tenant)->create();
        $own = DemoSale::factory()->for($tenant)->create();
        $foreignSale = DemoSale::factory()->for($foreign)->create();
        app(CurrentTenant::class)->set($tenant);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', DemoSale::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $own));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignSale));
    }

    public function test_policy_rejects_users_who_are_not_members_of_the_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $outsider = User::factory()->create();
        app(CurrentTenant::class)->set($tenant);

        $this->assertFalse(Gate::forUser($outsider)->allows('viewAny', DemoSale::class));
    }

    public function test_dashboard_detail_exposes_query_results_only_from_the_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();
        DemoSale::factory()->for($tenant)->create(['region' => 'Norte', 'total_amount' => 100, 'sold_at' => now()->subDays(2)]);
        DemoSale::factory()->for($foreign)->create(['region' => 'Norte', 'total_amount' => 50000, 'sold_at' => now()->subDays(2)]);
        $spec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($tenant->getKey(), $user->getKey(), 'ventas por region'))
            ->toArray();
        $spec['global_filters'] = [];
        $dashboard = Dashboard::factory()->for($tenant)->create([
            'created_by' => $user->getKey(),
            'status' => DashboardStatus::Ready,
            'spec_version' => '1.0',
            'dashboard_spec' => $spec,
        ]);

        $this->actingAs($user)
            ->withSession([(string) config('tenancy.session_key') => $tenant->getKey()])
            ->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visualizationData.total_sales.rows.0.total_sales', 100)
                ->where('visualizationData.sales_by_region.rows.0.region', 'Norte')
                ->where('visualizationData.sales_by_region.rows.0.total_sales', 100)
                ->has('visualizationData.sales_by_region.columns', 2));
    }

    public function test_the_period_query_parameter_recomputes_the_visualizations(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');
        $tenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();
        DemoSale::factory()->for($tenant)->create(['total_amount' => 10, 'sold_at' => '2026-08-10 10:00:00']);
        DemoSale::factory()->for($tenant)->create(['total_amount' => 100, 'sold_at' => '2026-11-05 10:00:00']);
        $spec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($tenant->getKey(), $user->getKey(), 'ventas por region'))
            ->toArray();
        $dashboard = Dashboard::factory()->for($tenant)->create([
            'created_by' => $user->getKey(),
            'status' => DashboardStatus::Ready,
            'spec_version' => '1.1',
            'dashboard_spec' => $spec,
        ]);

        // The fake spec filters the previous quarter, so only the August sale counts by default.
        $this->actingAs($user)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.value', 'spec')
                ->where('period.overrides_spec', false)
                ->where('period.range', null)
                ->has('period.options', 9)
                ->where('visualizationData.total_sales.rows.0.total_sales', 10));

        $this->actingAs($user)->get(route('dashboards.show', ['dashboard' => $dashboard, 'period' => 'this_month']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.value', 'this_month')
                ->where('period.label', 'Este mes')
                ->where('period.overrides_spec', true)
                ->where('period.date_field', 'sold_at')
                ->where('period.range', ['from' => '2026-11-01', 'to' => '2026-11-30'])
                ->where('visualizationData.total_sales.rows.0.total_sales', 100));

        $this->actingAs($user)->get(route('dashboards.show', ['dashboard' => $dashboard, 'period' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->where('visualizationData.total_sales.rows.0.total_sales', 110));
    }

    public function test_an_unknown_period_falls_back_to_the_dashboards_own_filters(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();
        $dashboard = Dashboard::factory()->for($tenant)->queued()->create();

        $this->actingAs($user)->get(route('dashboards.show', ['dashboard' => $dashboard, 'period' => 'drop table']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('period.value', 'spec'));
    }

    public function test_dashboard_detail_has_no_query_results_until_the_dashboard_is_ready(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();
        $dashboard = Dashboard::factory()->for($tenant)->queued()->create(['created_by' => $user->getKey()]);

        $this->actingAs($user)
            ->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('visualizationData', null));
    }

    public function test_foreign_dashboards_never_reach_the_executor(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();
        $foreignDashboard = Dashboard::factory()->for($foreign)->create(['status' => DashboardStatus::Ready]);

        $this->actingAs($user)
            ->get(route('dashboards.show', $foreignDashboard))
            ->assertForbidden();
    }
}
