<?php

namespace Tests\Feature\Privacy;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_policy_is_public_and_built_from_the_applications_own_facts(): void
    {
        config(['privacy.contact_email' => 'privacidad@ejemplo.test']);

        $this->get('/privacidad')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/Privacy')
                ->where('version', config('privacy.version'))
                ->where('updatedAt', config('privacy.updated_at'))
                ->where('contactEmail', 'privacidad@ejemplo.test')
                ->where('controller.name', 'Cifra Studios')
                ->where('retention.pendingUploadHours', 24)
                ->where('retention.sessionMinutes', config('session.lifetime'))
                ->has('processors', count(config('privacy.processors'))));
    }

    public function test_no_personal_address_is_published_unless_one_is_configured(): void
    {
        config(['privacy.contact_email' => null]);

        $this->get('/privacidad')->assertInertia(fn (Assert $page) => $page->where('contactEmail', null));
    }

    public function test_every_processor_that_handles_data_is_listed_with_what_it_receives(): void
    {
        $processors = collect(config('privacy.processors'));

        foreach (['Render', 'Neon', 'Google (API de Gemini)'] as $name) {
            $this->assertTrue($processors->contains('name', $name), "{$name} must be disclosed");
        }

        foreach ($processors as $processor) {
            $this->assertNotSame('', $processor['purpose']);
            $this->assertNotSame('', $processor['data']);
            $this->assertStringStartsWith('https://', $processor['url']);
        }

        $google = $processors->firstWhere('name', 'Google (API de Gemini)');
        $this->assertStringContainsString('Nunca tus filas', $google['data']);
    }

    public function test_the_policy_is_reachable_by_guests_and_signed_in_people_alike(): void
    {
        $this->get('/privacidad')->assertOk();

        $user = User::factory()->ownerOf(Tenant::factory()->create())->create();
        $this->actingAs($user)->get('/privacidad')->assertOk();
    }

    public function test_registering_requires_accepting_the_policy_and_records_what_was_accepted(): void
    {
        $payload = ['name' => 'Persona Prueba', 'email' => 'persona@example.test', 'password' => 'password', 'password_confirmation' => 'password'];

        $this->post('/register', $payload)->assertSessionHasErrors('privacy');
        $this->post('/register', [...$payload, 'privacy' => false])->assertSessionHasErrors('privacy');
        $this->assertNull(User::query()->where('email', 'persona@example.test')->first(), 'no account without consent');

        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        $this->post('/register', [...$payload, 'privacy' => true])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'persona@example.test')->sole();
        $this->assertSame(config('privacy.version'), $user->privacy_policy_version);
        $this->assertEquals(now()->startOfMinute(), $user->privacy_accepted_at->startOfMinute());
    }

    public function test_a_client_cannot_forge_a_different_accepted_version(): void
    {
        $this->post('/register', [
            'name' => 'Persona Prueba', 'email' => 'forjado@example.test', 'password' => 'password', 'password_confirmation' => 'password',
            'privacy' => true, 'privacy_policy_version' => '1999-01-01', 'privacy_accepted_at' => '1999-01-01 00:00:00',
        ]);

        $user = User::query()->where('email', 'forjado@example.test')->sole();
        $this->assertSame(config('privacy.version'), $user->privacy_policy_version);
        $this->assertNotSame('1999', $user->privacy_accepted_at->format('Y'));
    }

    public function test_the_policy_version_in_force_is_a_real_date_not_a_placeholder(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', config('privacy.version'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', config('privacy.updated_at'));
    }
}
