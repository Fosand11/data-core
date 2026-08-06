<?php

namespace Tests\Feature\Auth;

use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register'));
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $tenant = Tenant::query()->sole();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => auth()->id(),
            'role' => TenantRole::Owner->value,
        ]);
    }
}
