<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CurrentTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_select_a_current_tenant(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $user = User::factory()->ownerOf($first)->memberOf($second)->create();

        $response = $this->actingAs($user)
            ->patch(route('tenants.select', $second));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(
            (string) config('tenancy.session_key'),
            $second->getKey(),
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.slug', $second->slug)
                ->has('tenants', 2));
    }

    public function test_a_stale_cross_tenant_session_is_replaced_with_a_valid_membership(): void
    {
        $ownTenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($ownTenant)->create();

        $response = $this->actingAs($user)
            ->withSession([(string) config('tenancy.session_key') => $foreignTenant->getKey()])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSessionHas(
            (string) config('tenancy.session_key'),
            $ownTenant->getKey(),
        );
    }

    public function test_a_user_cannot_select_another_tenants_organization(): void
    {
        $ownTenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($ownTenant)->create();

        $this->actingAs($user)
            ->patch(route('tenants.select', $foreignTenant))
            ->assertForbidden();
    }

    public function test_policy_rejects_a_dashboard_from_another_tenant(): void
    {
        $ownTenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($ownTenant)->create();
        $dashboard = Dashboard::factory()->for($foreignTenant)->create();

        app(CurrentTenant::class)->set($ownTenant);

        $this->assertFalse(Gate::forUser($user)->allows('view', $dashboard));
    }

    public function test_dashboard_listing_is_scoped_to_the_current_tenant(): void
    {
        $ownTenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($ownTenant)->create();
        Dashboard::factory()->for($ownTenant)->create(['name' => 'Own dashboard']);
        Dashboard::factory()->for($foreignTenant)->create(['name' => 'Foreign dashboard']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboards', 1)
                ->where('dashboards.0.name', 'Own dashboard'));
    }
}
