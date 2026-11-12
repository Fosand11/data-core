<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Actions\StartDashboardGeneration;
use App\Domain\Dashboards\Enums\DashboardGenerationKind;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\RefineDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DashboardVersion;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardRefinementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dashboard-ai.driver' => 'fake']);
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->ownerOf($this->tenant)->create();
        $this->member = User::factory()->memberOf($this->tenant)->create();
    }

    // --- request ----------------------------------------------------------

    public function test_a_refinement_is_queued_without_touching_the_dashboard(): void
    {
        Queue::fake();
        $dashboard = $this->readyDashboard();
        $before = $dashboard->dashboard_spec;

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'cambia las barras por un gráfico circular'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $generation = $dashboard->generations()->sole();
        $this->assertSame(DashboardGenerationKind::Refine, $generation->kind);
        $this->assertSame(DashboardGenerationStatus::Queued, $generation->status);
        $this->assertSame('cambia las barras por un gráfico circular', $generation->prompt);
        $this->assertSame($this->tenant->getKey(), $generation->tenant_id);
        $this->assertSame($this->owner->getKey(), $generation->requested_by);
        $this->assertSame(1, $generation->base_version);
        Queue::assertPushed(RefineDashboardJob::class, fn (RefineDashboardJob $job): bool => $job->generationId === $generation->getKey());

        $dashboard->refresh();
        $this->assertSame(DashboardStatus::Ready, $dashboard->status);
        $this->assertEquals($before, $dashboard->dashboard_spec);
        $this->assertSame(1, $dashboard->versions()->count());
    }

    public function test_the_first_refinement_records_the_original_as_version_one(): void
    {
        Queue::fake();
        $dashboard = $this->readyDashboard();
        $this->assertSame(0, $dashboard->versions()->count());

        $this->request($dashboard, 'agrega una tabla de detalle');

        $original = $dashboard->versions()->sole();
        $this->assertSame(1, $original->number);
        $this->assertSame(DashboardVersion::SOURCE_GENERATED, $original->source);
        $this->assertEquals($dashboard->refresh()->dashboard_spec, $original->spec);
    }

    public function test_the_job_stores_a_validated_proposal_and_leaves_the_dashboard_unchanged(): void
    {
        $dashboard = $this->readyDashboard();
        $before = $dashboard->dashboard_spec;

        $this->request($dashboard, 'cambia las barras por un gráfico circular');

        $proposal = $dashboard->generations()->sole();
        $this->assertSame(DashboardGenerationStatus::Completed, $proposal->status);
        $this->assertSame('fake', $proposal->provider);
        $types = array_column($proposal->validated_spec['visualizations'], 'type');
        $this->assertSame(2, count(array_keys($types, 'pie')), 'the bar chart became a second pie');
        $this->assertNotContains('bar', $types);
        $this->assertEquals($before, $dashboard->refresh()->dashboard_spec);
        $this->assertSame(DashboardStatus::Ready, $dashboard->status);
    }

    public function test_an_instruction_that_changes_nothing_fails_with_a_helpful_message(): void
    {
        $dashboard = $this->readyDashboard();

        $this->request($dashboard, 'hazlo más bonito y moderno');

        $proposal = $dashboard->generations()->sole();
        $this->assertSame(DashboardGenerationStatus::Failed, $proposal->status);
        $this->assertSame('NO_CHANGES', $proposal->error_code);
        $this->assertStringContainsString('más específico', (string) $proposal->error_message);
        $this->assertSame(DashboardStatus::Ready, $dashboard->refresh()->status);
    }

    public function test_the_request_is_validated_and_never_accepts_a_tenant_id(): void
    {
        $dashboard = $this->readyDashboard();
        $foreign = Tenant::factory()->create();

        $this->actingAs($this->owner)->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'ab'])->assertSessionHasErrors('instruction');
        $this->actingAs($this->owner)->post(route('dashboards.refinements.store', $dashboard), ['instruction' => str_repeat('a', 501)])->assertSessionHasErrors('instruction');
        $this->actingAs($this->owner)->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'quita los filtros', 'tenant_id' => $foreign->getKey()])->assertSessionHasErrors('tenant_id');

        $this->assertSame(0, DashboardGeneration::query()->count());
    }

    public function test_only_ready_dashboards_can_be_refined(): void
    {
        Queue::fake();
        $failed = Dashboard::factory()->for($this->tenant)->create(['status' => DashboardStatus::Failed]);

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $failed), ['instruction' => 'quita los filtros'])
            ->assertSessionHasErrors('instruction');

        Queue::assertNothingPushed();
    }

    public function test_only_one_refinement_runs_at_a_time_and_a_stale_one_does_not_block(): void
    {
        Queue::fake();
        config(['dashboard-ai.stale_after_minutes' => 10]);
        $dashboard = $this->readyDashboard();

        $this->request($dashboard, 'quita los filtros');
        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'agrega una tabla'])
            ->assertSessionHasErrors('instruction');
        $this->assertSame(1, $dashboard->generations()->count());

        $this->travel(11)->minutes();

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'agrega una tabla'])
            ->assertSessionHasNoErrors();

        $abandoned = $dashboard->generations()->where('error_code', 'GENERATION_ABANDONED')->sole();
        $this->assertSame(DashboardGenerationStatus::Failed, $abandoned->status);
        $this->assertNotNull($abandoned->discarded_at);
        $this->assertSame(1, $dashboard->generations()->unresolvedRefinements()->count());
    }

    public function test_a_new_request_replaces_an_earlier_proposal_that_was_waiting(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'cambia las barras por un gráfico circular');
        $first = $dashboard->generations()->sole();

        $this->request($dashboard, 'agrega una tabla de detalle');

        $this->assertNotNull($first->refresh()->discarded_at);
        $this->assertSame(1, $dashboard->generations()->unresolvedRefinements()->count());
    }

    // --- apply / discard --------------------------------------------------

    public function test_applying_a_proposal_creates_version_two_and_makes_it_live(): void
    {
        $dashboard = $this->readyDashboard(['title' => 'Mi título manual']);
        $this->request($dashboard, 'agrega una tabla de detalle');
        $proposal = $dashboard->generations()->sole();

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $dashboard->refresh();
        $versions = $dashboard->versions()->get();
        $this->assertCount(2, $versions);
        $this->assertSame(DashboardVersion::SOURCE_REFINED, $versions[1]->source);
        $this->assertSame('agrega una tabla de detalle', $versions[1]->instruction);
        $this->assertSame($this->owner->getKey(), $versions[1]->created_by);
        $this->assertEquals($proposal->validated_spec, $dashboard->dashboard_spec);
        $this->assertContains('table', array_column($dashboard->dashboard_spec['visualizations'], 'type'));
        $this->assertNotNull($proposal->refresh()->applied_at);
        // The manual title survives because the change did not touch the spec title.
        $this->assertSame('Mi título manual', $dashboard->title);
    }

    public function test_the_title_follows_the_spec_only_when_the_change_modified_it(): void
    {
        $dashboard = $this->readyDashboard(['title' => 'Título viejo']);
        $this->request($dashboard, 'quita los filtros');
        $proposal = $dashboard->generations()->sole();
        $spec = $proposal->validated_spec;
        $spec['title'] = 'Título nuevo de la IA';
        $proposal->update(['validated_spec' => $spec]);

        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $proposal]));

        $this->assertSame('Título nuevo de la IA', $dashboard->refresh()->title);
    }

    public function test_a_proposal_that_no_longer_validates_is_never_applied(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'quita los filtros');
        $proposal = $dashboard->generations()->sole();
        $spec = $proposal->validated_spec;
        $spec['description'] = 'SELECT * FROM users WHERE 1=1';
        $proposal->update(['validated_spec' => $spec]);
        $before = $dashboard->refresh()->dashboard_spec;

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))
            ->assertSessionHasErrors('refinement');

        $this->assertEquals($before, $dashboard->refresh()->dashboard_spec);
        $this->assertSame(1, $dashboard->versions()->count());
        $this->assertNull($proposal->refresh()->applied_at);
    }

    public function test_a_stale_proposal_cannot_be_applied(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'quita los filtros');
        $proposal = $dashboard->generations()->sole();
        // Someone else changed the dashboard after the proposal was made.
        app(DashboardVersions::class)->record($dashboard, $dashboard->dashboard_spec, DashboardVersion::SOURCE_REFINED, 'otro cambio', $this->owner->getKey());

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))
            ->assertSessionHasErrors('refinement');

        $this->assertSame(2, $dashboard->versions()->count());
    }

    public function test_a_proposal_can_only_be_applied_once_and_not_after_discarding(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');
        $proposal = $dashboard->generations()->sole();

        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))->assertSessionHasErrors('refinement');
        $this->assertSame(2, $dashboard->versions()->count());

        $this->request($dashboard, 'quita los filtros');
        $second = $dashboard->generations()->unresolvedRefinements()->sole();
        $this->actingAs($this->owner)->delete(route('dashboards.refinements.discard', [$dashboard, $second]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $second]))->assertSessionHasErrors('refinement');
    }

    public function test_discarding_hides_the_proposal_and_keeps_the_dashboard(): void
    {
        $dashboard = $this->readyDashboard();
        $before = $dashboard->dashboard_spec;
        $this->request($dashboard, 'agrega una tabla de detalle');
        $proposal = $dashboard->generations()->sole();

        $this->actingAs($this->owner)
            ->delete(route('dashboards.refinements.discard', [$dashboard, $proposal]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Cambios descartados.');

        $this->assertNotNull($proposal->refresh()->discarded_at);
        $this->assertEquals($before, $dashboard->refresh()->dashboard_spec);
        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page->where('refinement', null));
    }

    public function test_a_discarded_run_is_dropped_when_the_job_finishes_late(): void
    {
        Queue::fake();
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');
        $proposal = $dashboard->generations()->sole();
        $this->actingAs($this->owner)->delete(route('dashboards.refinements.discard', [$dashboard, $proposal]));

        (new RefineDashboardJob($proposal->getKey()))->handle(app(DashboardGenerator::class), app(DashboardSpecValidator::class));

        $this->assertSame(DashboardGenerationStatus::Queued, $proposal->refresh()->status);
        $this->assertNull($proposal->validated_spec);
    }

    // --- restore / undo ---------------------------------------------------

    public function test_restoring_the_original_appends_a_new_version_and_can_itself_be_undone(): void
    {
        $dashboard = $this->readyDashboard();
        $original = $dashboard->dashboard_spec;
        $this->request($dashboard, 'agrega una tabla de detalle');
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $dashboard->generations()->sole()]));
        $refined = $dashboard->refresh()->dashboard_spec;

        $this->actingAs($this->owner)
            ->post(route('dashboards.versions.restore', [$dashboard, 1]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Se restauró la versión 1.');

        $dashboard->refresh();
        $versions = $dashboard->versions()->get();
        $this->assertCount(3, $versions);
        $this->assertSame(DashboardVersion::SOURCE_RESTORED, $versions[2]->source);
        $this->assertSame('Se restauró la versión 1.', $versions[2]->instruction);
        $this->assertEquals($original, $dashboard->dashboard_spec);

        // Undo the undo: version 2 is available again.
        $this->actingAs($this->owner)->post(route('dashboards.versions.restore', [$dashboard, 2]));
        $this->assertEquals($refined, $dashboard->refresh()->dashboard_spec);
        $this->assertSame(4, $dashboard->versions()->count());
    }

    public function test_restoring_rejects_the_current_or_a_missing_version(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');

        $this->actingAs($this->owner)->post(route('dashboards.versions.restore', [$dashboard, 1]))->assertSessionHasErrors('version');
        $this->actingAs($this->owner)->post(route('dashboards.versions.restore', [$dashboard, 99]))->assertSessionHasErrors('version');
        $this->assertSame(1, $dashboard->versions()->count());
    }

    public function test_restoring_discards_a_proposal_based_on_an_older_state(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $dashboard->generations()->sole()]));
        $this->request($dashboard, 'quita los filtros');
        $pending = $dashboard->generations()->unresolvedRefinements()->sole();

        $this->actingAs($this->owner)->post(route('dashboards.versions.restore', [$dashboard, 1]));

        $this->assertNotNull($pending->refresh()->discarded_at);
    }

    // --- permissions ------------------------------------------------------

    public function test_members_cannot_refine_apply_discard_or_restore(): void
    {
        Queue::fake();
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');
        $proposal = $dashboard->generations()->sole();

        $this->actingAs($this->member)->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'quita los filtros'])->assertForbidden();
        $this->actingAs($this->member)->post(route('dashboards.refinements.apply', [$dashboard, $proposal]))->assertForbidden();
        $this->actingAs($this->member)->delete(route('dashboards.refinements.discard', [$dashboard, $proposal]))->assertForbidden();
        $this->actingAs($this->member)->post(route('dashboards.versions.restore', [$dashboard, 1]))->assertForbidden();

        $this->assertSame(1, $dashboard->generations()->count());
    }

    public function test_other_tenants_dashboards_and_foreign_refinements_are_untouchable(): void
    {
        Queue::fake();
        $foreignTenant = Tenant::factory()->create();
        $foreign = Dashboard::factory()->for($foreignTenant)->create(['status' => DashboardStatus::Ready, 'dashboard_spec' => ['version' => '1.1']]);
        $foreignProposal = DashboardGeneration::factory()->create([
            'dashboard_id' => $foreign->getKey(),
            'tenant_id' => $foreignTenant->getKey(),
            'kind' => DashboardGenerationKind::Refine,
            'status' => DashboardGenerationStatus::Completed,
            'validated_spec' => ['version' => '1.1'],
        ]);
        $mine = $this->readyDashboard();

        $this->actingAs($this->owner)->post(route('dashboards.refinements.store', $foreign), ['instruction' => 'quita los filtros'])->assertForbidden();
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$foreign, $foreignProposal]))->assertForbidden();
        $this->actingAs($this->owner)->post(route('dashboards.versions.restore', [$foreign, 1]))->assertForbidden();
        // My dashboard with somebody else's refinement id.
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$mine, $foreignProposal]))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('dashboards.refinements.discard', [$mine, $foreignProposal]))->assertNotFound();

        $this->assertNull($foreignProposal->refresh()->applied_at);
        $this->assertNull($foreignProposal->discarded_at);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $dashboard = $this->readyDashboard();

        $this->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'quita los filtros'])->assertRedirect(route('login'));
    }

    // --- page payload -----------------------------------------------------

    public function test_the_detail_page_exposes_the_proposal_its_data_and_the_history(): void
    {
        DemoSale::factory()->for($this->tenant)->create(['total_amount' => 100, 'region' => 'Norte', 'sold_at' => now()->subMonths(4)]);
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'quita los filtros');

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.can.refine', true)
                ->where('refinement.status', 'completed')
                ->where('refinement.instruction', 'quita los filtros')
                ->where('refinement.is_stale', false)
                ->where('refinement.proposed_spec.global_filters', [])
                // With the previous-quarter filter gone, the old sale is now counted.
                ->where('proposalData.total_sales.rows.0.total_sales', 100)
                ->has('versions', 1)
                ->where('versions.0.number', 1)
                ->where('versions.0.is_current', true));

        $this->actingAs($this->member)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.can.refine', false)
                ->where('refinement', null)
                ->where('proposalData', null));
    }

    public function test_the_history_is_listed_newest_first(): void
    {
        $dashboard = $this->readyDashboard();
        $this->request($dashboard, 'agrega una tabla de detalle');
        $this->actingAs($this->owner)->post(route('dashboards.refinements.apply', [$dashboard, $dashboard->generations()->sole()]));

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->has('versions', 2)
                ->where('versions.0.number', 2)
                ->where('versions.0.source', 'refined')
                ->where('versions.0.instruction', 'agrega una tabla de detalle')
                ->where('versions.0.is_current', true)
                ->where('versions.1.number', 1)
                ->where('versions.1.is_current', false));
    }

    public function test_the_detail_page_generation_info_ignores_refinement_runs(): void
    {
        $dashboard = $this->readyDashboard();
        DashboardGeneration::factory()->create([
            'dashboard_id' => $dashboard->getKey(),
            'tenant_id' => $this->tenant->getKey(),
            'kind' => DashboardGenerationKind::Create,
            'provider' => 'gemini',
            'model' => 'gemini-x',
            'status' => DashboardGenerationStatus::Completed,
        ]);
        $this->request($dashboard, 'agrega una tabla de detalle');

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.generation.model', 'gemini-x'));
    }

    public function test_a_newly_generated_dashboard_starts_with_version_one(): void
    {
        app(CurrentTenant::class)->set($this->tenant);

        $dashboard = app(StartDashboardGeneration::class)->execute($this->owner, 'ventas por region del ultimo trimestre');

        $version = $dashboard->refresh()->versions()->sole();
        $this->assertSame(1, $version->number);
        $this->assertSame(DashboardVersion::SOURCE_GENERATED, $version->source);
        $this->assertEquals($dashboard->dashboard_spec, $version->spec);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function readyDashboard(array $attributes = []): Dashboard
    {
        $spec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput($this->tenant->getKey(), $this->owner->getKey(), 'ventas por region'))
            ->toArray();

        return Dashboard::factory()->for($this->tenant)->create([
            'created_by' => $this->owner->getKey(),
            'status' => DashboardStatus::Ready,
            'title' => $spec['title'],
            'description' => $spec['description'],
            'spec_version' => '1.1',
            'dashboard_spec' => $spec,
            'original_prompt' => 'ventas por region',
            ...$attributes,
        ]);
    }

    private function request(Dashboard $dashboard, string $instruction): void
    {
        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $dashboard), ['instruction' => $instruction])
            ->assertSessionHasNoErrors();
    }
}
