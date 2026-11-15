<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Assistant\HelpAssistant;
use App\Domain\Ai\Assistant\HelpIntent;
use App\Domain\Ai\Assistant\QuestionSuggester;
use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Jobs\RefineDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Support\DatasetCatalogResolver;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "What can I do?": answered from fixed content, before any generation, without using the
 * generation allowance, and only ever with the current organization's own column names.
 */
class HelpAssistantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dashboard-ai.driver' => 'fake']);
        $this->tenant = Tenant::factory()->create();
        $this->other = Tenant::factory()->create();
        $this->owner = User::factory()->ownerOf($this->tenant)->create();
    }

    public function test_a_question_about_the_visuals_is_answered_and_no_dashboard_is_created(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->from(route('dashboard'))
            ->post(route('dashboards.store'), ['prompt' => '¿Qué visuales tienes?'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('assistant.kind', 'visuals');

        $this->assertSame(0, Dashboard::withoutTenantScope()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_reply_reaches_the_page_once_and_then_is_gone(): void
    {
        $this->actingAs($this->owner)->from(route('dashboard'))->post(route('dashboards.store'), ['prompt' => '¿Qué puedo hacer?']);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.assistant.kind', 'overview')
                ->has('flash.assistant.suggestions', 4)
                ->has('flash.assistant.tips', 5));

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.assistant', null));
    }

    public function test_the_visual_list_names_all_twelve_types_with_an_example_each(): void
    {
        $this->actingAs($this->owner)->from(route('dashboard'))->post(route('dashboards.store'), ['prompt' => 'qué gráficas hay']);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(function (Assert $page) {
                $page->has('flash.assistant.visuals', 12);
                $types = collect($page->toArray()['props']['flash']['assistant']['visuals']);

                $this->assertEqualsCanonicalizing(
                    ['kpi', 'bar', 'hbar', 'line', 'area', 'pie', 'funnel', 'treemap', 'radar', 'scatter', 'gauge', 'table'],
                    $types->pluck('type')->all(),
                );
                $this->assertTrue($types->every(fn (array $visual): bool => $visual['example'] !== '' && $visual['description'] !== ''));
            });
    }

    public function test_help_questions_do_not_use_up_the_generation_allowance(): void
    {
        Queue::fake();

        // The limiter allows a handful of real requests a minute; help answers must not count.
        foreach (range(1, 12) as $attempt) {
            $this->actingAs($this->owner)->from(route('dashboard'))
                ->post(route('dashboards.store'), ['prompt' => 'sugiéreme algo'])
                ->assertSessionHas('assistant.kind', 'suggestions');
        }

        $this->actingAs($this->owner)->post(route('dashboards.store'), ['prompt' => 'Ventas por región'])->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateDashboardJob::class);
    }

    public function test_a_real_request_still_creates_a_dashboard(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)->post(route('dashboards.store'), ['prompt' => 'Ventas por región'])
            ->assertSessionMissing('assistant');

        $this->assertSame(1, Dashboard::withoutTenantScope()->count());
    }

    public function test_suggestions_for_a_dataset_use_its_own_column_names(): void
    {
        $dataset = $this->datasetWithColumns($this->tenant, 'Clientes', [
            ['pais', 'País', 'string', 's1'],
            ['plan', 'Plan', 'string', 's2'],
            ['ingreso', 'Ingreso mensual', 'numeric', 'n1'],
            ['gasto', 'Gasto mensual', 'numeric', 'n2'],
            ['registro', 'Fecha de registro', 'datetime', 'd1'],
        ]);

        $this->actingAs($this->owner)->from(route('dashboard'))
            ->post(route('dashboards.store'), ['prompt' => 'sugiéreme algo', 'dataset_id' => $dataset->getKey()])
            ->assertSessionHas('assistant.dataset', 'Clientes');

        $reply = session('assistant');
        $text = implode(' | ', $reply['suggestions']);

        $this->assertStringContainsString('Ingreso mensual por País', $text);
        $this->assertStringContainsString('Evolución mensual de Ingreso mensual', $text);
        $this->assertStringContainsString('Ingreso mensual contra Gasto mensual', $text);
        $this->assertStringNotContainsString('Ventas por región', $text, 'the demo questions do not belong to this data');
    }

    public function test_identifying_columns_are_not_suggested_as_axes(): void
    {
        $dataset = $this->datasetWithColumns($this->tenant, 'Personas', [
            ['id_cliente', 'ID cliente', 'string', 's1'],
            ['correo', 'Correo', 'string', 's2'],
            ['pais', 'País', 'string', 's3'],
            ['ingreso', 'Ingreso', 'numeric', 'n1'],
        ]);
        $suggestions = app(QuestionSuggester::class)->suggest($this->catalogOf($dataset));

        $this->assertStringContainsString('Ingreso por País', implode(' | ', $suggestions));
        $this->assertStringNotContainsString('ID cliente', implode(' | ', $suggestions));
        $this->assertStringNotContainsString('Correo', implode(' | ', $suggestions));
    }

    public function test_visuals_the_data_cannot_support_are_marked_with_the_reason(): void
    {
        $dataset = $this->datasetWithColumns($this->tenant, 'Solo texto', [['pais', 'País', 'string', 's1']]);
        $reply = app(HelpAssistant::class)->reply(HelpIntent::Visuals, $this->catalogOf($dataset), 'Solo texto');
        $byType = collect($reply['visuals'])->keyBy('type');

        $this->assertTrue($byType['bar']['available']);
        $this->assertFalse($byType['line']['available']);
        $this->assertSame('Tus datos no tienen una columna de fecha.', $byType['line']['note']);
        $this->assertFalse($byType['scatter']['available']);
        $this->assertFalse($byType['gauge']['available']);
        $this->assertTrue($byType['kpi']['available']);
    }

    public function test_another_organizations_dataset_id_gets_the_demo_answer_and_no_foreign_names(): void
    {
        $foreign = $this->datasetWithColumns($this->other, 'Secreto ajeno', [
            ['cliente_vip', 'Cliente VIP confidencial', 'string', 's1'],
            ['ingreso', 'Ingreso confidencial', 'numeric', 'n1'],
        ]);

        $this->actingAs($this->owner)->from(route('dashboard'))
            ->post(route('dashboards.store'), ['prompt' => 'sugiéreme algo', 'dataset_id' => $foreign->getKey()])
            ->assertSessionHas('assistant.kind', 'suggestions');

        $reply = session('assistant');

        $this->assertNull($reply['dataset']);
        $this->assertStringNotContainsString('confidencial', json_encode($reply, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Secreto', json_encode($reply, JSON_UNESCAPED_UNICODE));
        $this->assertContains('Ventas por región', $reply['suggestions']);
    }

    public function test_a_malformed_dataset_id_is_ignored_instead_of_failing(): void
    {
        $this->actingAs($this->owner)->from(route('dashboard'))
            ->post(route('dashboards.store'), ['prompt' => 'qué visuales tienes', 'dataset_id' => "x'; drop table datasets;--"])
            ->assertSessionHas('assistant.kind', 'visuals');
    }

    public function test_a_dataset_that_is_not_ready_is_not_used(): void
    {
        $pending = $this->datasetWithColumns($this->tenant, 'Pendiente', [['pais', 'País', 'string', 's1']], DatasetStatus::Pending);

        $this->actingAs($this->owner)->from(route('dashboard'))
            ->post(route('dashboards.store'), ['prompt' => 'sugiéreme algo', 'dataset_id' => $pending->getKey()]);

        $this->assertNull(session('assistant')['dataset']);
    }

    public function test_on_a_dashboard_the_suggestions_are_changes_to_ask_for(): void
    {
        Queue::fake();
        $dashboard = $this->readyDashboard($this->tenant);

        $this->actingAs($this->owner)->from(route('dashboards.show', $dashboard))
            ->post(route('dashboards.refinements.store', $dashboard), ['instruction' => 'sugiéreme algo'])
            ->assertSessionHas('assistant.kind', 'suggestions');

        $this->assertContains('Cambia las barras por un gráfico circular', session('assistant')['suggestions']);
        $this->assertSame(0, $dashboard->generations()->count(), 'no refinement was requested');
        Queue::assertNotPushed(RefineDashboardJob::class);
    }

    public function test_another_organizations_dashboard_still_does_not_exist(): void
    {
        $foreign = $this->readyDashboard($this->other);

        $this->actingAs($this->owner)
            ->post(route('dashboards.refinements.store', $foreign), ['instruction' => 'sugiéreme algo'])
            ->assertNotFound();
    }

    public function test_guests_are_sent_to_log_in_not_answered(): void
    {
        $this->post(route('dashboards.store'), ['prompt' => '¿Qué visuales tienes?'])->assertRedirect(route('login'));
    }

    public function test_the_limits_are_honest_about_what_does_not_exist(): void
    {
        $reply = app(HelpAssistant::class)->reply(HelpIntent::Limits, null);
        $text = implode(' ', $reply['limits']);

        $this->assertStringContainsString('mapas geográficos', $text);
        $this->assertStringContainsString('Nunca envío tus filas', $text);
        $this->assertStringContainsString((string) config('datasets.max_columns').' columnas', $text);
    }

    // --- helpers -----------------------------------------------------------------------------

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $columns  key, label, type, slot
     */
    private function datasetWithColumns(Tenant $tenant, string $name, array $columns, DatasetStatus $status = DatasetStatus::Ready): Dataset
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant, $name, $columns, $status): Dataset {
            $dataset = Dataset::factory()->for($tenant)->create(['name' => $name, 'status' => $status]);

            foreach ($columns as $position => [$key, $label, $type, $slot]) {
                $dataset->columns()->create([
                    'tenant_id' => $tenant->getKey(),
                    'position' => $position,
                    'key' => $key,
                    'label' => $label,
                    'type' => $type,
                    'slot' => $slot,
                    'included' => true,
                    'samples' => [],
                ]);
            }

            return $dataset;
        });
    }

    private function catalogOf(Dataset $dataset): DatasetCatalog
    {
        return app(TenantContext::class)->run($dataset->tenant, fn () => app(DatasetCatalogResolver::class)->forDataset($dataset->load('columns')));
    }

    private function readyDashboard(Tenant $tenant): Dashboard
    {
        return app(TenantContext::class)->run($tenant, fn (): Dashboard => Dashboard::factory()->for($tenant)->create([
            'created_by' => null,
            'status' => DashboardStatus::Ready,
            'spec_version' => '1.2',
            'dashboard_spec' => ['version' => '1.2', 'title' => 'Spec', 'visualizations' => [['key' => 'k', 'type' => 'kpi']]],
            'original_prompt' => 'una pregunta cualquiera',
        ]));
    }
}
