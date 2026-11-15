<?php

namespace Tests\Feature\Dashboards\Datasets;

use App\Domain\Dashboards\Datasets\DatasetFileReader;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Jobs\ImportDatasetJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Models\DatasetColumn;
use App\Domain\Dashboards\Models\DatasetRow;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The upload feature as a browser sees it: routes, validation, authorization, and the
 * rule that another organization's dataset simply does not exist.
 */
class DatasetHttpTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "Región,Monto,Fecha\nNorte,100,2025-01-15\nSur,200,2025-02-20\n";

    private Tenant $tenant;

    private Tenant $other;

    private User $owner;

    private User $member;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->tenant = Tenant::factory()->create();
        $this->other = Tenant::factory()->create();
        $this->owner = User::factory()->ownerOf($this->tenant)->create();
        $this->member = User::factory()->memberOf($this->tenant)->create();
        $this->outsider = User::factory()->ownerOf($this->other)->create();
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $dataset = $this->datasetOf($this->tenant);

        $this->get(route('datasets.index'))->assertRedirect(route('login'));
        $this->get(route('datasets.show', $dataset))->assertRedirect(route('login'));
        $this->post(route('datasets.store'), ['file' => $this->csv()])->assertRedirect(route('login'));
        $this->delete(route('datasets.destroy', $dataset))->assertRedirect(route('login'));
    }

    // --- listing and showing ------------------------------------------------------------

    public function test_the_index_lists_only_the_current_organizations_datasets_with_the_limits(): void
    {
        $mine = $this->datasetOf($this->tenant, ['name' => 'Ventas mías']);
        $this->datasetOf($this->other, ['name' => 'Ventas ajenas']);

        $this->actingAs($this->owner)
            ->get(route('datasets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('datasets/Index')
                ->has('datasets', 1)
                ->where('datasets.0.id', $mine->getKey())
                ->where('datasets.0.name', 'Ventas mías')
                ->where('limits.max_datasets', 5)
                ->where('limits.slots.numeric', 12)
                ->where('can.create', true));
    }

    public function test_members_can_look_but_not_upload(): void
    {
        $dataset = $this->datasetOf($this->tenant);

        $this->actingAs($this->member)->get(route('datasets.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        $this->actingAs($this->member)->get(route('datasets.show', $dataset))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.delete', false)->where('can.update', false));

        $this->actingAs($this->member)->post(route('datasets.store'), ['file' => $this->csv()])->assertForbidden();
        $this->actingAs($this->member)->delete(route('datasets.destroy', $dataset))->assertForbidden();
        $this->assertNotNull(Dataset::withoutTenantScope()->find($dataset->getKey()));
    }

    public function test_the_show_page_of_a_ready_dataset_has_its_columns_and_a_preview_of_its_rows(): void
    {
        $dataset = $this->readyDataset();

        $this->actingAs($this->owner)
            ->get(route('datasets.show', $dataset))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('datasets/Show')
                ->where('dataset.status', 'ready')
                ->where('dataset.row_count', 2)
                ->has('columns', 3)
                ->has('preview', 2)
                ->where('preview.0.region', 'Norte')
                ->where('preview.0.monto', 100)
                ->where('can.delete', true));
    }

    // --- uploading and confirming ---------------------------------------------------------

    public function test_an_owner_uploads_reviews_and_confirms_a_file(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)->post(route('datasets.store'), ['file' => $this->csv()]);

        $dataset = Dataset::withoutTenantScope()->sole();
        $response->assertRedirect(route('datasets.show', $dataset));
        $this->assertSame(DatasetStatus::Pending, $dataset->status);
        $this->assertSame($this->tenant->getKey(), $dataset->tenant_id);
        Queue::assertNotPushed(ImportDatasetJob::class);

        $this->actingAs($this->owner)->get(route('datasets.show', $dataset))
            ->assertInertia(fn (Assert $page) => $page->where('dataset.status', 'pending')->has('columns', 3));

        $this->actingAs($this->owner)
            ->post(route('datasets.import', $dataset), ['name' => 'Mis ventas', 'columns' => $this->choices($dataset)])
            ->assertRedirect(route('datasets.show', $dataset));

        Queue::assertPushed(ImportDatasetJob::class);
        $this->assertSame(DatasetStatus::Importing, $dataset->refresh()->status);
        $this->assertSame('Mis ventas', $dataset->name);
    }

    public function test_a_missing_file_an_old_excel_format_and_an_oversized_file_are_rejected(): void
    {
        $this->actingAs($this->owner)->post(route('datasets.store'), [])->assertSessionHasErrors('file');
        $this->actingAs($this->owner)->post(route('datasets.store'), ['file' => UploadedFile::fake()->createWithContent('viejo.xls', 'x')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->owner)->post(route('datasets.store'), ['file' => UploadedFile::fake()->create('grande.csv', (int) config('datasets.max_file_kb') + 1)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Dataset::withoutTenantScope()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_tenant_id_in_the_request_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('datasets.store'), ['file' => $this->csv(), 'tenant_id' => $this->other->getKey()])
            ->assertSessionHasErrors('tenant_id');

        $this->assertSame(0, Dataset::withoutTenantScope()->count());
    }

    public function test_a_file_that_is_not_really_a_spreadsheet_is_reported_on_the_file_field(): void
    {
        $this->actingAs($this->owner)
            ->post(route('datasets.store'), ['file' => UploadedFile::fake()->createWithContent('roto.xlsx', 'esto no es un zip')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Dataset::withoutTenantScope()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // --- another organization's datasets do not exist --------------------------------------

    public function test_every_dataset_route_answers_404_for_another_organizations_dataset(): void
    {
        $foreign = $this->datasetOf($this->other);

        $this->actingAs($this->owner)->get(route('datasets.show', $foreign))->assertNotFound();
        $this->actingAs($this->owner)->post(route('datasets.import', $foreign), ['name' => 'x', 'columns' => []])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('datasets.destroy', $foreign))->assertNotFound();

        $this->assertNotNull(Dataset::withoutTenantScope()->find($foreign->getKey()));
    }

    // --- using a dataset -----------------------------------------------------------------

    public function test_a_dashboard_can_be_created_on_a_ready_dataset(): void
    {
        Queue::fake();
        $dataset = $this->readyDataset();

        $this->actingAs($this->owner)
            ->post(route('dashboards.store'), ['prompt' => 'Ventas por región', 'dataset_id' => $dataset->getKey()])
            ->assertRedirect();

        $dashboard = Dashboard::withoutTenantScope()->sole();
        $this->assertSame($dataset->getKey(), $dashboard->dataset_id);
        Queue::assertPushed(GenerateDashboardJob::class);
    }

    public function test_a_dataset_of_another_organization_or_not_yet_ready_cannot_be_used(): void
    {
        Queue::fake();
        $foreign = $this->datasetOf($this->other);
        $pending = Dataset::factory()->pending()->for($this->tenant)->create();

        foreach ([$foreign, $pending] as $dataset) {
            $this->actingAs($this->owner)
                ->post(route('dashboards.store'), ['prompt' => 'Ventas por región', 'dataset_id' => $dataset->getKey()])
                ->assertSessionHasErrors('dataset_id');
        }

        $this->assertSame(0, Dashboard::withoutTenantScope()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_create_page_offers_only_ready_datasets_of_the_organization(): void
    {
        $mine = $this->readyDataset();
        Dataset::factory()->pending()->for($this->tenant)->create();
        $this->datasetOf($this->other);

        $this->actingAs($this->owner)
            ->get(route('dashboards.create', ['dataset' => $mine->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboards/Create')
                ->has('datasets', 1)
                ->where('datasetId', $mine->getKey()));

        // A dataset of someone else in the query string is ignored, not honoured.
        $foreign = $this->datasetOf($this->other);
        $this->actingAs($this->owner)
            ->get(route('dashboards.create', ['dataset' => $foreign->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('datasetId', null));
    }

    public function test_a_dashboard_on_a_dataset_without_dates_has_no_period_filter(): void
    {
        $dataset = $this->readyDataset();
        $dashboard = Dashboard::factory()->for($this->tenant)->create([
            'created_by' => $this->owner->getKey(),
            'dataset_id' => $dataset->getKey(),
            'status' => 'ready',
            'spec_version' => '1.1',
            'dashboard_spec' => ['version' => '1.1', 'title' => 'Spec', 'dataset' => $dataset->specKey(), 'visualizations' => [['type' => 'kpi', 'key' => 'k']]],
            'original_prompt' => 'una pregunta cualquiera',
        ]);
        // The sample has a date column, so a period has something to filter.
        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.dataset.id', $dataset->getKey())->where('period.date_field', 'fecha'));

        DatasetColumn::withoutTenantScope()->where('dataset_id', $dataset->getKey())->where('type', 'datetime')->delete();

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('period.date_field', null));
    }

    public function test_deleting_a_dataset_through_http_removes_its_data(): void
    {
        $dataset = $this->readyDataset();

        $this->actingAs($this->owner)->delete(route('datasets.destroy', $dataset))->assertRedirect(route('datasets.index'));

        $this->assertNull(Dataset::withoutTenantScope()->find($dataset->getKey()));
        $this->assertSame(0, DatasetRow::withoutTenantScope()->count());
        $this->assertSame(0, DatasetColumn::withoutTenantScope()->count());
    }

    // --- helpers -----------------------------------------------------------------------------

    private function csv(string $content = self::CSV): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ventas.csv', $content);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function datasetOf(Tenant $tenant, array $attributes = []): Dataset
    {
        // Entered like a job would: a record is never created for a tenant other than the current one.
        return app(TenantContext::class)->run($tenant, fn (): Dataset => Dataset::factory()->for($tenant)->create($attributes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function choices(Dataset $dataset): array
    {
        return DatasetColumn::withoutTenantScope()->where('dataset_id', $dataset->getKey())->orderBy('position')->get()
            ->map(fn (DatasetColumn $column): array => ['id' => (string) $column->getKey(), 'label' => $column->label, 'type' => $column->type->value, 'included' => true])
            ->all();
    }

    /**
     * A dataset imported for real (upload, confirm, run the job) so the page has rows to show.
     */
    private function readyDataset(): Dataset
    {
        Queue::fake();
        $this->actingAs($this->owner)->post(route('datasets.store'), ['file' => $this->csv()]);
        $dataset = Dataset::withoutTenantScope()->where('tenant_id', $this->tenant->getKey())->latest()->firstOrFail();
        $this->actingAs($this->owner)->post(route('datasets.import', $dataset), ['name' => 'Ventas', 'columns' => $this->choices($dataset)]);

        app(TenantContext::class)->run($this->tenant, fn () => (new ImportDatasetJob((string) $dataset->getKey()))
            ->handle(app(DatasetFileReader::class)));

        // The queue was faked above, which is also what later assertions on pushes rely on.
        Queue::fake();

        return $dataset->refresh();
    }
}
