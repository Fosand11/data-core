<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Queries\DashboardQueryExecutor;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 1.2 visualization types, end to end below the HTTP layer: the query executor
 * returns what each chart needs, and the deterministic provider can build them on a
 * dataset it has never seen.
 */
class DashboardVisualTypesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($this->tenant);

        foreach ([['Norte', 100, 2], ['Norte', 50, 1], ['Sur', 30, 3], ['Este', 80, 4]] as [$region, $amount, $quantity]) {
            DemoSale::factory()->for($this->tenant)->create(['region' => $region, 'total_amount' => $amount, 'quantity' => $quantity]);
        }
    }

    public function test_the_executor_returns_rows_for_every_new_type(): void
    {
        $spec = DashboardSpec::fromArray([
            'version' => '1.2',
            'title' => 'Todos',
            'description' => '',
            'dataset' => 'demo_sales',
            'global_filters' => [],
            'visualizations' => [
                $this->visualization('ranking', 'hbar', ['x_axis' => 'region', 'y_axis' => 'total'], [$this->metric('total', 'sum', 'total_amount')], 0),
                $this->visualization('stages', 'funnel', ['category' => 'region', 'value' => 'total'], [$this->metric('total', 'sum', 'total_amount')], 4, limit: 8),
                $this->visualization('shares', 'treemap', ['category' => 'region', 'value' => 'total'], [$this->metric('total', 'sum', 'total_amount')], 8, limit: 20),
                $this->visualization('spokes', 'radar', ['category' => 'region', 'value' => 'total'], [$this->metric('total', 'sum', 'total_amount')], 12, limit: 8),
                $this->visualization('relation', 'scatter', ['x_axis' => 'total', 'y_axis' => 'units', 'category' => 'region'], [$this->metric('total', 'sum', 'total_amount'), $this->metric('units', 'sum', 'quantity')], 16, limit: 100),
                $this->visualization('score', 'gauge', ['value' => 'avg_units', 'max' => 10], [$this->metric('avg_units', 'avg', 'quantity')], 20, dimensions: [], limit: 1),
            ],
            'layout' => ['columns' => 12],
        ]);

        $results = app(DashboardQueryExecutor::class)->execute($spec);

        // The same validator that guards generation accepts it, then the executor runs it.
        $this->assertSame($spec, (new DashboardSpecValidator)->validate($spec));
        $this->assertEqualsCanonicalizing(['Norte', 'Sur', 'Este'], array_column($results['ranking']['rows'], 'region'));
        $this->assertSame(150.0, $this->rowFor($results['stages'], 'Norte')['total']);
        $this->assertCount(3, $results['shares']['rows']);
        $this->assertCount(3, $results['spokes']['rows']);

        // A scatter row carries both measures for each point.
        $north = $this->rowFor($results['relation'], 'Norte');
        $this->assertSame(150.0, $north['total']);
        $this->assertSame(3.0, (float) $north['units']);

        // A gauge is one number, like a KPI: (2 + 1 + 3 + 4) / 4.
        $this->assertCount(1, $results['score']['rows']);
        $this->assertSame(2.5, (float) $results['score']['rows'][0]['avg_units']);
    }

    public function test_the_fake_provider_builds_the_requested_visuals_on_any_dataset(): void
    {
        $catalog = $this->catalog();
        $generator = new FakeDashboardGenerator;
        $validator = (new DashboardSpecValidator)->forCatalog($catalog);
        $prompt = 'Quiero un embudo, un treemap, un radar, una dispersión, un medidor y un ranking horizontal';

        $spec = $generator->generate(new DashboardGenerationInput('tenant', 1, $prompt, catalog: $catalog));
        $types = array_column($spec->toArray()['visualizations'], 'type');

        $this->assertSame($spec, $validator->validate($spec));
        $this->assertEqualsCanonicalizing(['funnel', 'treemap', 'radar', 'scatter', 'gauge', 'hbar'], array_intersect($types, ['funnel', 'treemap', 'radar', 'scatter', 'gauge', 'hbar']));

        // Without seeing a single row, the gauge takes its scale from the header ("1-10").
        $gauge = collect($spec->toArray()['visualizations'])->firstWhere('type', 'gauge');
        $this->assertSame(10.0, (float) $gauge['display']['max']);
        $this->assertSame('score', $gauge['query']['metrics'][0]['field']);
    }

    public function test_the_fake_provider_skips_visuals_the_columns_cannot_support(): void
    {
        $catalog = $this->catalog(numeric: ['amount']);
        $spec = (new FakeDashboardGenerator)->generate(new DashboardGenerationInput('tenant', 1, 'una dispersión y un embudo', catalog: $catalog));
        $types = array_column($spec->toArray()['visualizations'], 'type');

        // One numeric column cannot draw a scatter, but a funnel needs only one measure.
        $this->assertNotContains('scatter', $types);
        $this->assertContains('funnel', $types);
        $this->assertSame($spec, (new DashboardSpecValidator)->forCatalog($catalog)->validate($spec));
    }

    public function test_the_fake_provider_does_not_group_by_identifiers_names_or_contact_columns(): void
    {
        $catalog = $this->catalog(texts: ['id_cliente', 'nombre_completo', 'correo', 'telefono', 'pais']);
        $spec = (new FakeDashboardGenerator)->generate(new DashboardGenerationInput('tenant', 1, 'un ranking horizontal y un embudo', catalog: $catalog))->toArray();

        $dimensions = collect($spec['visualizations'])
            ->filter(fn (array $visualization): bool => $visualization['query']['dimensions'] !== [])
            ->map(fn (array $visualization): string => $visualization['query']['dimensions'][0]['field'])
            ->reject(fn (string $field): bool => $field === 'sold_on'); // the trend over time groups by the date

        $this->assertSame(['pais'], $dimensions->unique()->values()->all());
        $this->assertSame($spec, (new DashboardSpecValidator)->forCatalog($catalog)->validate(DashboardSpec::fromArray($spec))->toArray());
    }

    public function test_a_refinement_can_switch_a_chart_to_a_funnel_or_a_radar(): void
    {
        $generator = new FakeDashboardGenerator;
        $base = $generator->generate(new DashboardGenerationInput('tenant', 1, 'ventas por región'))->toArray();

        foreach (['embudo' => 'funnel', 'radar' => 'radar', 'barras horizontales' => 'hbar', 'treemap' => 'treemap'] as $word => $type) {
            $changed = $generator->generate(new DashboardGenerationInput('tenant', 1, "cambia el gráfico por un {$word}", currentSpec: $base))->toArray();

            $this->assertContains($type, array_column($changed['visualizations'], 'type'), "'{$word}' should produce a {$type}");
            $this->assertSame($changed, (new DashboardSpecValidator)->validate(DashboardSpec::fromArray($changed))->toArray());
        }
    }

    // --- helpers -----------------------------------------------------------------------------

    /**
     * @param  list<string>  $numeric
     * @param  list<string>  $texts
     */
    private function catalog(array $numeric = ['amount', 'score'], array $texts = ['channel', 'city']): DatasetCatalog
    {
        $fields = array_fill_keys($texts, 'string') + ['sold_on' => 'datetime'];

        foreach ($numeric as $field) {
            $fields[$field] = 'numeric';
        }

        return new class($fields) implements DatasetCatalog
        {
            /** @param array<string, string> $fields */
            public function __construct(private readonly array $fields) {}

            public function dataset(): string
            {
                return 'ds_fixture';
            }

            public function fields(): array
            {
                return $this->fields;
            }

            public function labels(): array
            {
                return ['channel' => 'Canal', 'city' => 'Ciudad', 'amount' => 'Monto', 'score' => 'Satisfacción (1-10)'];
            }

            public function hasField(string $field): bool
            {
                return array_key_exists($field, $this->fields);
            }

            public function typeOf(string $field): ?string
            {
                return $this->fields[$field] ?? null;
            }

            public function dateField(): ?string
            {
                return 'sold_on';
            }

            public function column(string $field): string
            {
                return $field;
            }
        };
    }

    /**
     * @param  array{rows: list<array<string, mixed>>}  $result
     * @return array<string, mixed>
     */
    private function rowFor(array $result, string $region): array
    {
        return collect($result['rows'])->firstWhere('region', $region) ?? $this->fail("no row for {$region}");
    }

    /**
     * @return array<string, string>
     */
    private function metric(string $alias, string $aggregation, string $field): array
    {
        return ['field' => $field, 'aggregation' => $aggregation, 'alias' => $alias];
    }

    /**
     * @param  array<string, mixed>  $display
     * @param  list<array<string, string>>  $metrics
     * @param  list<array<string, string>>|null  $dimensions
     * @return array<string, mixed>
     */
    private function visualization(string $key, string $type, array $display, array $metrics, int $y, ?array $dimensions = null, int $limit = 6): array
    {
        return [
            'key' => $key,
            'title' => $key,
            'type' => $type,
            'query' => ['metrics' => $metrics, 'dimensions' => $dimensions ?? [['field' => 'region']], 'filters' => [], 'order_by' => [], 'limit' => $limit],
            'display' => $display,
            'layout' => ['x' => 0, 'y' => $y, 'width' => 12, 'height' => 4],
        ];
    }
}
