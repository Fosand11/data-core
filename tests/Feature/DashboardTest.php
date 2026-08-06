<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_shows_an_empty_state_for_the_current_tenant()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->ownerOf($tenant)->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('currentTenant.slug', $tenant->slug)
            ->has('tenants', 1)
            ->has('dashboards', 0));
    }
}
