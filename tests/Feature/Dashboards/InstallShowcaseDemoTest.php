<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Queries\DashboardQueryExecutor;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallShowcaseDemoTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-demo-password-1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.name' => 'Demo',
            'demo.email' => 'Demo@Example.Test',
            'demo.password' => self::PASSWORD,
            'tenancy.seed_demo_data' => true,
        ]);
    }

    public function test_it_creates_the_owner_the_sample_data_and_ready_dashboards(): void
    {
        $this->artisan('demo:install')->assertSuccessful();

        $user = User::query()->where('email', 'demo@example.test')->sole();
        $tenant = $user->tenants()->sole();

        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertGreaterThan(0, DemoSale::query()->forTenant($tenant)->count());

        $dashboards = Dashboard::query()->where('tenant_id', $tenant->getKey())->get();

        $this->assertCount(3, $dashboards);
        $this->assertTrue($dashboards->every(fn (Dashboard $dashboard): bool => $dashboard->status === DashboardStatus::Ready));
        $this->assertTrue($dashboards->every(fn (Dashboard $dashboard): bool => $dashboard->versions()->count() === 1));
    }

    public function test_every_sample_dashboard_runs_against_the_demo_data(): void
    {
        $this->artisan('demo:install')->assertSuccessful();

        $tenant = User::query()->where('email', 'demo@example.test')->sole()->tenants()->sole();
        app(CurrentTenant::class)->set($tenant);
        $executor = $this->app->make(DashboardQueryExecutor::class);

        foreach (Dashboard::query()->where('tenant_id', $tenant->getKey())->get() as $dashboard) {
            $results = $executor->execute(DashboardSpec::fromArray($dashboard->dashboard_spec));

            $this->assertNotEmpty($results, "{$dashboard->title} has no results");

            foreach ($results as $key => $result) {
                $this->assertNotEmpty($result['rows'], "{$dashboard->title} / {$key} came back empty");
            }
        }
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->artisan('demo:install')->assertSuccessful();
        $this->artisan('demo:install')->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(3, Dashboard::query()->count());
    }

    public function test_demo_dashboards_are_invisible_to_other_organizations(): void
    {
        $this->artisan('demo:install')->assertSuccessful();

        $demoDashboard = Dashboard::query()->firstOrFail();
        $stranger = Tenant::factory()->create();
        $outsider = User::factory()->ownerOf($stranger)->create();

        $this->actingAs($outsider)
            ->get(route('dashboards.show', $demoDashboard))
            ->assertForbidden();
    }

    public function test_a_missing_or_weak_password_is_rejected_without_creating_anything(): void
    {
        config(['demo.password' => null]);
        $this->artisan('demo:install')->assertFailed();

        config(['demo.password' => 'too-short']);
        $this->artisan('demo:install')->assertFailed();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Dashboard::query()->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        config(['demo.email' => 'not-an-email']);

        $this->artisan('demo:install')->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
