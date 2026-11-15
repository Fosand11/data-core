<?php

namespace Tests\Feature\Privacy;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DashboardVersion;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Models\DatasetRow;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The right to be forgotten: deleting an account removes the person and everything only they
 * had, and never touches anyone else's data.
 */
class AccountErasureTest extends TestCase
{
    use RefreshDatabase;

    /** Every table that can hold an organization's data. */
    private const TENANT_TABLES = ['dashboards', 'dashboard_generations', 'dashboard_versions', 'dashboard_pins', 'datasets', 'dataset_columns', 'dataset_rows', 'demo_sales'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_deleting_the_account_erases_the_organization_and_every_trace_of_its_data(): void
    {
        $leaving = User::factory()->create(['email' => 'leaving@example.test']);
        $tenant = Tenant::factory()->create();
        $tenant->attachUser($leaving, TenantRole::Owner);
        $this->fillOrganization($tenant, $leaving);
        $path = 'datasets/'.$tenant->getKey().'/pending.csv';
        Storage::disk('local')->put($path, "a,b\n1,2\n");
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $leaving->getKey(), 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => now()->timestamp]);
        DB::table('password_reset_tokens')->insert(['email' => 'leaving@example.test', 'token' => 'hash', 'created_at' => now()]);

        // Another organization, to prove nothing of it is touched.
        $bystander = User::factory()->create();
        $other = Tenant::factory()->create();
        $other->attachUser($bystander, TenantRole::Owner);
        $this->fillOrganization($other, $bystander);
        $otherPath = 'datasets/'.$other->getKey().'/keep.csv';
        Storage::disk('local')->put($otherPath, "x\n");
        $before = $this->countsFor($other);

        $this->actingAs($leaving)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(User::query()->find($leaving->getKey()));
        $this->assertNull(Tenant::query()->find($tenant->getKey()), 'the organization nobody else belongs to is deleted');
        $this->assertDatabaseMissing('tenant_user', ['tenant_id' => $tenant->getKey()]);

        foreach (self::TENANT_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $tenant->getKey())->count(), "{$table} still holds the erased organization's rows");
        }

        $this->assertDatabaseMissing('sessions', ['user_id' => $leaving->getKey()]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'leaving@example.test']);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists($otherPath);

        $this->assertNotNull(Tenant::query()->find($other->getKey()));
        $this->assertSame($before, $this->countsFor($other), 'another organization lost data');
    }

    public function test_a_wrong_password_deletes_nothing(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->attachUser($user, TenantRole::Owner);
        $this->fillOrganization($tenant, $user);

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertNotNull(User::query()->find($user->getKey()));
        $this->assertNotNull(Tenant::query()->find($tenant->getKey()));
        $this->assertGreaterThan(0, DB::table('dashboards')->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_a_shared_organization_keeps_going_and_never_loses_its_last_owner(): void
    {
        $leaving = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->attachUser($leaving, TenantRole::Owner);
        $tenant->attachUser($first, TenantRole::Member);
        $this->travel(1)->minute();
        $tenant->attachUser($second, TenantRole::Admin);
        $this->fillOrganization($tenant, $leaving);

        $this->actingAs($leaving)->delete('/settings/profile', ['password' => 'password']);

        $this->assertNull(User::query()->find($leaving->getKey()));
        $this->assertNotNull(Tenant::query()->find($tenant->getKey()), 'the organization still has people in it');
        $this->assertGreaterThan(0, DB::table('dashboards')->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(
            TenantRole::Owner->value,
            DB::table('tenant_user')->where('tenant_id', $tenant->getKey())->where('user_id', $first->getKey())->value('role'),
            'the longest-standing remaining member takes over',
        );
        $this->assertSame(
            TenantRole::Admin->value,
            DB::table('tenant_user')->where('tenant_id', $tenant->getKey())->where('user_id', $second->getKey())->value('role'),
            'nobody else changes role',
        );
        // What the leaving person created stays with the organization, no longer attributed to them.
        $this->assertSame(0, DB::table('dashboards')->where('created_by', $leaving->getKey())->count());
    }

    public function test_when_another_owner_exists_nobody_is_promoted(): void
    {
        $leaving = User::factory()->create();
        $coOwner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->attachUser($leaving, TenantRole::Owner);
        $tenant->attachUser($coOwner, TenantRole::Owner);
        $tenant->attachUser($member, TenantRole::Member);

        $this->actingAs($leaving)->delete('/settings/profile', ['password' => 'password']);

        $this->assertSame(TenantRole::Member->value, DB::table('tenant_user')->where('user_id', $member->getKey())->value('role'));
        $this->assertSame(TenantRole::Owner->value, DB::table('tenant_user')->where('user_id', $coOwner->getKey())->value('role'));
    }

    public function test_a_person_in_two_organizations_loses_only_the_one_nobody_else_is_in(): void
    {
        $leaving = User::factory()->create();
        $friend = User::factory()->create();
        $solo = Tenant::factory()->create();
        $shared = Tenant::factory()->create();
        $solo->attachUser($leaving, TenantRole::Owner);
        $shared->attachUser($leaving, TenantRole::Member);
        $shared->attachUser($friend, TenantRole::Owner);
        $this->fillOrganization($solo, $leaving);
        $this->fillOrganization($shared, $friend);

        $this->actingAs($leaving)->delete('/settings/profile', ['password' => 'password']);

        $this->assertNull(Tenant::query()->find($solo->getKey()));
        $this->assertNotNull(Tenant::query()->find($shared->getKey()));
        $this->assertGreaterThan(0, DB::table('datasets')->where('tenant_id', $shared->getKey())->count());
    }

    // --- helpers -----------------------------------------------------------------------------

    /**
     * Data in every table an organization owns.
     */
    private function fillOrganization(Tenant $tenant, User $author): void
    {
        app(TenantContext::class)->run($tenant, function () use ($tenant, $author): void {
            $dashboard = Dashboard::factory()->for($tenant)->create([
                'created_by' => $author->getKey(),
                'status' => DashboardStatus::Ready,
                'spec_version' => '1.2',
                'dashboard_spec' => ['version' => '1.2', 'title' => 'Spec', 'visualizations' => [['key' => 'k', 'type' => 'kpi']]],
                'original_prompt' => 'datos privados de prueba',
            ]);
            DashboardGeneration::factory()->for($dashboard)->create(['tenant_id' => $tenant->getKey(), 'requested_by' => $author->getKey()]);
            app(DashboardVersions::class)->ensureInitial($dashboard);
            $dashboard->pinnedBy()->attach($author->getKey(), ['tenant_id' => $tenant->getKey()]);

            $dataset = Dataset::factory()->for($tenant)->create(['status' => DatasetStatus::Ready, 'created_by' => $author->getKey(), 'row_count' => 1]);
            $dataset->columns()->create([
                'tenant_id' => $tenant->getKey(), 'position' => 0, 'key' => 'pais', 'label' => 'País', 'type' => 'string', 'slot' => 's1', 'included' => true, 'samples' => [],
            ]);
            (new DatasetRow)->forceFill(['tenant_id' => $tenant->getKey(), 'dataset_id' => $dataset->getKey(), 's1' => 'El Salvador'])->save();

            DemoSale::factory()->for($tenant)->create();
        });
    }

    /**
     * @return array<string, int>
     */
    private function countsFor(Tenant $tenant): array
    {
        $counts = [];

        foreach (self::TENANT_TABLES as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenant->getKey())->count();
        }

        $this->assertGreaterThan(0, array_sum($counts), 'the fixture must actually hold data');
        $this->assertGreaterThan(0, DashboardVersion::withoutTenantScope()->where('tenant_id', $tenant->getKey())->count());

        return $counts;
    }
}
