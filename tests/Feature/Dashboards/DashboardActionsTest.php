<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardActionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->ownerOf($this->tenant)->create();
        $this->member = User::factory()->memberOf($this->tenant)->create();
    }

    // --- update -----------------------------------------------------------

    public function test_an_owner_can_rename_a_dashboard_and_edit_its_description(): void
    {
        $dashboard = $this->readyDashboard(['title' => 'Antes', 'description' => 'vieja']);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.update', $dashboard), ['title' => '  Ventas 2026  ', 'description' => '  Resumen anual  '])
            ->assertRedirect()
            ->assertSessionHas('status', 'Cambios guardados.');

        $dashboard->refresh();
        $this->assertSame('Ventas 2026', $dashboard->title);
        $this->assertSame('Resumen anual', $dashboard->description);
    }

    public function test_an_empty_description_is_stored_as_null(): void
    {
        $dashboard = $this->readyDashboard(['description' => 'algo']);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.update', $dashboard), ['title' => 'Título', 'description' => '   '])
            ->assertRedirect();

        $this->assertNull($dashboard->refresh()->description);
    }

    public function test_rename_is_validated_and_never_accepts_a_tenant_id(): void
    {
        $dashboard = $this->readyDashboard(['title' => 'Intacto']);
        $foreign = Tenant::factory()->create();

        $this->actingAs($this->owner)
            ->patch(route('dashboards.update', $dashboard), ['title' => '', 'description' => str_repeat('x', 501), 'tenant_id' => $foreign->getKey()])
            ->assertSessionHasErrors(['title', 'description', 'tenant_id']);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.update', $dashboard), ['title' => str_repeat('t', 121)])
            ->assertSessionHasErrors('title');

        $this->assertSame('Intacto', $dashboard->refresh()->title);
        $this->assertSame($this->tenant->getKey(), $dashboard->tenant_id);
    }

    public function test_a_member_cannot_rename_a_dashboard(): void
    {
        $dashboard = $this->readyDashboard(['title' => 'Intacto']);

        $this->actingAs($this->member)
            ->patch(route('dashboards.update', $dashboard), ['title' => 'Cambiado'])
            ->assertForbidden();

        $this->assertSame('Intacto', $dashboard->refresh()->title);
    }

    public function test_a_dashboard_from_another_tenant_cannot_be_renamed(): void
    {
        $foreign = Dashboard::factory()->for(Tenant::factory()->create())->create(['title' => 'Ajeno']);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.update', $foreign), ['title' => 'Mío ahora'])
            ->assertForbidden();

        $this->assertSame('Ajeno', $foreign->refresh()->title);
    }

    // --- delete -----------------------------------------------------------

    public function test_an_owner_can_delete_a_dashboard_with_its_generations_and_pins(): void
    {
        $dashboard = $this->readyDashboard();
        DashboardGeneration::factory()->create(['dashboard_id' => $dashboard->getKey(), 'tenant_id' => $this->tenant->getKey()]);
        $dashboard->pinnedBy()->attach($this->owner->getKey(), ['tenant_id' => $this->tenant->getKey()]);

        $this->actingAs($this->owner)
            ->delete(route('dashboards.destroy', $dashboard))
            ->assertRedirect(route('dashboards.index'))
            ->assertSessionHas('status', 'Dashboard eliminado.');

        $this->assertModelMissing($dashboard);
        $this->assertDatabaseCount('dashboard_generations', 0);
        $this->assertDatabaseCount('dashboard_pins', 0);
    }

    public function test_a_member_cannot_delete_and_foreign_dashboards_are_untouchable(): void
    {
        $dashboard = $this->readyDashboard();
        $foreign = Dashboard::factory()->for(Tenant::factory()->create())->create();

        $this->actingAs($this->member)->delete(route('dashboards.destroy', $dashboard))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('dashboards.destroy', $foreign))->assertForbidden();

        $this->assertModelExists($dashboard);
        $this->assertModelExists($foreign);
    }

    // --- duplicate --------------------------------------------------------

    public function test_a_ready_dashboard_can_be_duplicated_as_an_independent_copy(): void
    {
        $source = $this->readyDashboard(['title' => 'Ventas', 'description' => 'Resumen', 'original_prompt' => 'ventas por región']);

        $response = $this->actingAs($this->owner)->post(route('dashboards.duplicate', $source));

        $copy = Dashboard::query()->whereKeyNot($source->getKey())->sole();
        $response->assertRedirect(route('dashboards.show', $copy))->assertSessionHas('status', 'Dashboard duplicado.');

        $this->assertSame('Ventas (copia)', $copy->title);
        $this->assertSame($source->description, $copy->description);
        $this->assertSame($source->original_prompt, $copy->original_prompt);
        // jsonb (PostgreSQL) does not preserve key order, so compare the content, not the order.
        $this->assertEquals($source->refresh()->dashboard_spec, $copy->dashboard_spec);
        $this->assertSame(DashboardStatus::Ready, $copy->status);
        $this->assertSame($this->tenant->getKey(), $copy->tenant_id);
        $this->assertSame($this->owner->getKey(), $copy->created_by);
        $this->assertSame(0, $copy->generations()->count());
        $this->assertSame('Ventas', $source->refresh()->title);
    }

    public function test_the_copy_title_never_exceeds_the_title_limit(): void
    {
        $source = $this->readyDashboard(['title' => str_repeat('a', 120)]);

        $this->actingAs($this->owner)->post(route('dashboards.duplicate', $source));

        $copy = Dashboard::query()->whereKeyNot($source->getKey())->sole();
        $this->assertLessThanOrEqual(120, mb_strlen($copy->title));
        $this->assertStringEndsWith(' (copia)', $copy->title);
    }

    public function test_only_ready_dashboards_can_be_duplicated(): void
    {
        $failed = Dashboard::factory()->for($this->tenant)->create(['status' => DashboardStatus::Failed]);

        $this->actingAs($this->owner)
            ->post(route('dashboards.duplicate', $failed))
            ->assertSessionHasErrors('dashboard');

        $this->assertDatabaseCount('dashboards', 1);
    }

    public function test_members_and_foreign_users_cannot_duplicate(): void
    {
        $source = $this->readyDashboard();
        $foreign = Dashboard::factory()->for(Tenant::factory()->create())->create(['status' => DashboardStatus::Ready, 'dashboard_spec' => ['visualizations' => []]]);

        $this->actingAs($this->member)->post(route('dashboards.duplicate', $source))->assertForbidden();
        $this->actingAs($this->owner)->post(route('dashboards.duplicate', $foreign))->assertForbidden();

        $this->assertDatabaseCount('dashboards', 2);
    }

    // --- pin --------------------------------------------------------------

    public function test_any_member_can_pin_and_unpin_for_themselves_only(): void
    {
        $dashboard = $this->readyDashboard();

        $this->actingAs($this->member)
            ->patch(route('dashboards.pin', $dashboard), ['pinned' => true])
            ->assertRedirect()
            ->assertSessionHas('status', 'Fijado en tu barra lateral.');

        $this->assertDatabaseHas('dashboard_pins', [
            'dashboard_id' => $dashboard->getKey(),
            'user_id' => $this->member->getKey(),
            'tenant_id' => $this->tenant->getKey(),
        ]);
        $this->assertDatabaseMissing('dashboard_pins', ['user_id' => $this->owner->getKey()]);

        $this->actingAs($this->member)->patch(route('dashboards.pin', $dashboard), ['pinned' => false])->assertRedirect();

        $this->assertDatabaseCount('dashboard_pins', 0);
    }

    public function test_pinning_twice_is_idempotent(): void
    {
        $dashboard = $this->readyDashboard();

        $this->actingAs($this->owner)->patch(route('dashboards.pin', $dashboard), ['pinned' => true]);
        $this->actingAs($this->owner)->patch(route('dashboards.pin', $dashboard), ['pinned' => true]);

        $this->assertDatabaseCount('dashboard_pins', 1);
    }

    public function test_the_pin_request_is_validated_and_foreign_dashboards_are_forbidden(): void
    {
        $dashboard = $this->readyDashboard();
        $foreign = Dashboard::factory()->for(Tenant::factory()->create())->create();

        $this->actingAs($this->owner)->patch(route('dashboards.pin', $dashboard), [])->assertSessionHasErrors('pinned');
        $this->actingAs($this->owner)->patch(route('dashboards.pin', $foreign), ['pinned' => true])->assertForbidden();

        $this->assertDatabaseCount('dashboard_pins', 0);
    }

    public function test_pinned_dashboards_are_listed_first_and_shared_with_the_sidebar_per_user(): void
    {
        $older = $this->readyDashboard(['title' => 'Antiguo', 'created_at' => now()->subDays(3)]);
        $newer = $this->readyDashboard(['title' => 'Nuevo', 'created_at' => now()->subHour()]);
        $older->pinnedBy()->attach($this->owner->getKey(), ['tenant_id' => $this->tenant->getKey()]);

        $this->actingAs($this->owner)
            ->get(route('dashboards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboards.0.title', 'Antiguo')
                ->where('dashboards.0.is_pinned', true)
                ->where('dashboards.1.title', 'Nuevo')
                ->where('dashboards.1.is_pinned', false)
                ->has('pinnedDashboards', 1)
                ->where('pinnedDashboards.0.id', $older->getKey()));

        // Favourites are personal.
        $this->actingAs($this->member)
            ->get(route('dashboards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboards.0.title', 'Nuevo')
                ->where('dashboards.0.is_pinned', false)
                ->has('pinnedDashboards', 0));

        $this->assertNotNull($newer);
    }

    // --- retry ------------------------------------------------------------

    public function test_a_failed_dashboard_can_be_retried_with_its_original_prompt(): void
    {
        Queue::fake();
        config(['dashboard-ai.driver' => 'fake']);
        $dashboard = Dashboard::factory()->for($this->tenant)->create([
            'created_by' => $this->owner->getKey(),
            'status' => DashboardStatus::Failed,
            'original_prompt' => 'ventas por región del último trimestre',
            'error_message' => 'falló antes',
            'provider' => 'gemini',
        ]);

        $this->actingAs($this->owner)
            ->post(route('dashboards.retry', $dashboard))
            ->assertRedirect()
            ->assertSessionHas('status', 'Generando de nuevo…');

        $dashboard->refresh();
        $generation = $dashboard->generations()->sole();

        $this->assertSame(DashboardStatus::Queued, $dashboard->status);
        $this->assertNull($dashboard->error_message);
        $this->assertSame('fake', $dashboard->provider);
        $this->assertSame('ventas por región del último trimestre', $generation->prompt);
        $this->assertSame(DashboardGenerationStatus::Queued, $generation->status);
        $this->assertSame($this->tenant->getKey(), $generation->tenant_id);
        $this->assertSame($this->owner->getKey(), $generation->requested_by);
        Queue::assertPushed(GenerateDashboardJob::class, fn (GenerateDashboardJob $job): bool => $job->generationId === $generation->getKey());
    }

    public function test_a_retry_actually_completes_when_the_job_runs(): void
    {
        config(['dashboard-ai.driver' => 'fake']);
        $dashboard = Dashboard::factory()->for($this->tenant)->create([
            'created_by' => $this->owner->getKey(),
            'status' => DashboardStatus::Failed,
            'original_prompt' => 'ventas por region del ultimo trimestre',
        ]);

        // QUEUE_CONNECTION=sync in tests: the retried job runs inline.
        $this->actingAs($this->owner)->post(route('dashboards.retry', $dashboard))->assertRedirect();

        $this->assertSame(DashboardStatus::Ready, $dashboard->refresh()->status);
        $this->assertNotNull($dashboard->dashboard_spec);
    }

    public function test_only_failed_dashboards_with_a_prompt_can_be_retried(): void
    {
        Queue::fake();
        $ready = $this->readyDashboard();
        $legacy = Dashboard::factory()->for($this->tenant)->create(['status' => DashboardStatus::Failed, 'original_prompt' => null]);

        $this->actingAs($this->owner)->post(route('dashboards.retry', $ready))->assertSessionHasErrors('dashboard');
        $this->actingAs($this->owner)->post(route('dashboards.retry', $legacy))->assertSessionHasErrors('dashboard');

        Queue::assertNothingPushed();
        $this->assertSame(DashboardStatus::Ready, $ready->refresh()->status);
    }

    public function test_a_stuck_generation_can_be_retried_but_a_fresh_one_cannot(): void
    {
        Queue::fake();
        config(['dashboard-ai.driver' => 'fake', 'dashboard-ai.stale_after_minutes' => 10]);
        $fresh = Dashboard::factory()->for($this->tenant)->create([
            'status' => DashboardStatus::Generating,
            'original_prompt' => 'ventas por región del último trimestre',
        ]);

        $this->actingAs($this->owner)->post(route('dashboards.retry', $fresh))->assertSessionHasErrors('dashboard');
        Queue::assertNothingPushed();

        $this->travel(11)->minutes();

        $this->actingAs($this->owner)->post(route('dashboards.retry', $fresh))->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateDashboardJob::class);
        $this->assertSame(DashboardStatus::Queued, $fresh->refresh()->status);
    }

    public function test_retrying_closes_the_interrupted_attempt_so_it_cannot_resurface(): void
    {
        Queue::fake();
        config(['dashboard-ai.driver' => 'fake']);
        $dashboard = Dashboard::factory()->for($this->tenant)->create([
            'status' => DashboardStatus::Failed,
            'original_prompt' => 'ventas por región del último trimestre',
        ]);
        $interrupted = DashboardGeneration::factory()->create([
            'dashboard_id' => $dashboard->getKey(),
            'tenant_id' => $this->tenant->getKey(),
            'status' => DashboardGenerationStatus::Processing,
        ]);

        $this->actingAs($this->owner)->post(route('dashboards.retry', $dashboard))->assertSessionHasNoErrors();

        $interrupted->refresh();
        $this->assertSame(DashboardGenerationStatus::Failed, $interrupted->status);
        $this->assertSame('GENERATION_ABANDONED', $interrupted->error_code);
        $this->assertSame(1, $dashboard->generations()->where('status', DashboardGenerationStatus::Queued)->count());
    }

    public function test_the_detail_page_only_offers_retry_when_it_makes_sense(): void
    {
        $failed = Dashboard::factory()->for($this->tenant)->create(['status' => DashboardStatus::Failed, 'original_prompt' => 'algo suficientemente largo']);
        $ready = $this->readyDashboard();

        $this->actingAs($this->owner)->get(route('dashboards.show', $failed))
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.can.retry', true));
        $this->actingAs($this->owner)->get(route('dashboards.show', $ready))
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.can.retry', false));
        $this->actingAs($this->member)->get(route('dashboards.show', $failed))
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.can.retry', false));
    }

    public function test_members_and_foreign_users_cannot_retry(): void
    {
        Queue::fake();
        $failed = Dashboard::factory()->for($this->tenant)->create(['status' => DashboardStatus::Failed, 'original_prompt' => 'algo suficientemente largo']);
        $foreign = Dashboard::factory()->for(Tenant::factory()->create())->create(['status' => DashboardStatus::Failed, 'original_prompt' => 'algo suficientemente largo']);

        $this->actingAs($this->member)->post(route('dashboards.retry', $failed))->assertForbidden();
        $this->actingAs($this->owner)->post(route('dashboards.retry', $foreign))->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(DashboardStatus::Failed, $failed->refresh()->status);
    }

    // --- detail page and flash -------------------------------------------

    public function test_the_detail_page_exposes_what_the_current_user_can_do(): void
    {
        $dashboard = $this->readyDashboard();

        $this->actingAs($this->owner)
            ->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.can.update', true)
                ->where('dashboard.can.duplicate', true)
                ->where('dashboard.can.delete', true)
                ->where('dashboard.is_pinned', false));

        $this->actingAs($this->member)
            ->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.can.update', false)
                ->where('dashboard.can.duplicate', false)
                ->where('dashboard.can.delete', false));
    }

    public function test_a_flashed_status_message_is_shared_with_the_page(): void
    {
        $dashboard = $this->readyDashboard();

        $this->actingAs($this->owner)
            ->withSession(['status' => 'Hecho.'])
            ->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page->where('flash.status', 'Hecho.'));
    }

    public function test_without_a_flashed_message_the_shared_status_is_null(): void
    {
        $dashboard = $this->readyDashboard();

        $this->actingAs($this->owner)
            ->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page->where('flash.status', null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function readyDashboard(array $attributes = []): Dashboard
    {
        return Dashboard::factory()->for($this->tenant)->create([
            'created_by' => $this->owner->getKey(),
            'status' => DashboardStatus::Ready,
            'spec_version' => '1.1',
            'dashboard_spec' => ['version' => '1.1', 'title' => 'Spec', 'visualizations' => [['type' => 'kpi', 'key' => 'k']]],
            'original_prompt' => 'una pregunta cualquiera',
            ...$attributes,
        ]);
    }
}
