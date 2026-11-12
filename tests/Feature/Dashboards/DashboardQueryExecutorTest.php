<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Dashboards\Enums\DashboardPeriod;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Queries\DashboardQueryExecutor;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class DashboardQueryExecutorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 12:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($this->tenant);
    }

    public function test_aggregates_by_dimension_for_the_current_tenant_only(): void
    {
        $this->sale(['region' => 'Norte', 'total_amount' => 100]);
        $this->sale(['region' => 'Norte', 'total_amount' => 50]);
        $this->sale(['region' => 'Sur', 'total_amount' => 30]);
        $this->sale(['region' => 'Norte', 'total_amount' => 9999], $this->otherTenant);

        $result = $this->execute($this->chart(order: [['field' => 'total', 'direction' => 'desc']]));

        $this->assertSame([
            ['region' => 'Norte', 'total' => 150.0],
            ['region' => 'Sur', 'total' => 30.0],
        ], $result['by_region']['rows']);
        $this->assertSame('dimension', $result['by_region']['columns'][0]['role']);
        $this->assertSame('currency', $result['by_region']['columns'][1]['format']);
    }

    public function test_a_tenant_without_data_gets_empty_results_not_foreign_data(): void
    {
        $this->sale(['region' => 'Norte'], $this->otherTenant);

        $result = $this->execute($this->chart());

        $this->assertSame([], $result['by_region']['rows']);
    }

    public function test_kpi_without_dimensions_returns_a_single_row(): void
    {
        $this->sale(['total_amount' => 10]);
        $this->sale(['total_amount' => 30]);

        $spec = $this->spec([$this->visualization('avg_ticket', 'kpi', metrics: [
            ['field' => 'total_amount', 'aggregation' => 'avg', 'alias' => 'avg_ticket'],
            ['field' => 'product_id', 'aggregation' => 'count', 'alias' => 'orders'],
        ], dimensions: [], display: ['show_comparison' => false])]);

        $row = $this->execute($spec)['avg_ticket']['rows'];

        $this->assertSame([['avg_ticket' => 20.0, 'orders' => 2]], $row);
    }

    public function test_previous_quarter_covers_the_whole_previous_calendar_quarter(): void
    {
        // "Now" is 2026-11-12, so the previous quarter is Jul 1 - Sep 30 2026.
        $this->sale(['sold_at' => '2026-06-30 23:59:59', 'total_amount' => 1]);
        $this->sale(['sold_at' => '2026-07-01 00:00:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-09-30 23:59:59', 'total_amount' => 100]);
        $this->sale(['sold_at' => '2026-10-01 00:00:00', 'total_amount' => 1000]);

        $spec = $this->spec(
            [$this->visualization('total', 'kpi', metrics: [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total']], dimensions: [], display: ['show_comparison' => false])],
            [['field' => 'sold_at', 'operator' => 'previous_quarter', 'value' => null]],
        );

        $this->assertSame(110.0, $this->execute($spec)['total']['rows'][0]['total']);
    }

    public function test_previous_period_is_the_previous_calendar_month(): void
    {
        $this->sale(['sold_at' => '2026-09-30 23:59:59', 'total_amount' => 1]);
        $this->sale(['sold_at' => '2026-10-15 10:00:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-11-01 00:00:00', 'total_amount' => 100]);

        $spec = $this->spec(
            [$this->visualization('total', 'kpi', metrics: [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total']], dimensions: [], display: ['show_comparison' => false])],
            [['field' => 'sold_at', 'operator' => 'previous_period', 'value' => null]],
        );

        $this->assertSame(10.0, $this->execute($spec)['total']['rows'][0]['total']);
    }

    public function test_datetime_dimension_is_bucketed_by_month_in_chronological_order(): void
    {
        $this->sale(['sold_at' => '2026-10-20 09:00:00', 'total_amount' => 5]);
        $this->sale(['sold_at' => '2026-09-02 09:00:00', 'total_amount' => 7]);
        $this->sale(['sold_at' => '2026-10-02 09:00:00', 'total_amount' => 3]);

        $spec = $this->spec([$this->visualization('trend', 'line', metrics: [
            ['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total'],
        ], dimensions: [['field' => 'sold_at']], display: ['x_axis' => 'sold_at', 'y_axis' => 'total'])]);

        $result = $this->execute($spec)['trend'];

        $this->assertSame([
            ['sold_at' => '2026-09', 'total' => 7.0],
            ['sold_at' => '2026-10', 'total' => 8.0],
        ], $result['rows']);
        $this->assertSame('month', $result['columns'][0]['type']);
    }

    public function test_between_with_date_only_bounds_includes_the_whole_last_day(): void
    {
        $this->sale(['sold_at' => '2026-03-31 18:30:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-04-01 00:00:00', 'total_amount' => 100]);

        $spec = $this->singleKpi([
            ['field' => 'sold_at', 'operator' => 'between', 'value' => ['2026-03-01', '2026-03-31']],
        ]);

        $this->assertSame(10.0, $this->execute($spec)['total']['rows'][0]['total']);
    }

    public function test_equality_membership_comparison_and_contains_filters(): void
    {
        $this->sale(['region' => 'Norte', 'quantity' => 1, 'product_name' => 'Laptop Pro', 'total_amount' => 1]);
        $this->sale(['region' => 'Sur', 'quantity' => 5, 'product_name' => '100% Cotton', 'total_amount' => 10]);
        $this->sale(['region' => 'Este', 'quantity' => 9, 'product_name' => 'Webcam', 'total_amount' => 100]);

        $this->assertSame(1.0, $this->sumWith(['field' => 'region', 'operator' => 'equals', 'value' => 'Norte']));
        $this->assertSame(110.0, $this->sumWith(['field' => 'region', 'operator' => 'not_equals', 'value' => 'Norte']));
        $this->assertSame(11.0, $this->sumWith(['field' => 'region', 'operator' => 'in', 'value' => ['Norte', 'Sur']]));
        $this->assertSame(100.0, $this->sumWith(['field' => 'region', 'operator' => 'not_in', 'value' => ['Norte', 'Sur']]));
        $this->assertSame(100.0, $this->sumWith(['field' => 'quantity', 'operator' => 'greater_than', 'value' => 5]));
        $this->assertSame(110.0, $this->sumWith(['field' => 'quantity', 'operator' => 'greater_than_or_equal', 'value' => 5]));
        $this->assertSame(11.0, $this->sumWith(['field' => 'quantity', 'operator' => 'between', 'value' => [1, 5]]));
        $this->assertSame(1.0, $this->sumWith(['field' => 'product_name', 'operator' => 'contains', 'value' => 'LAPTOP']));
    }

    public function test_like_wildcards_in_contains_are_treated_literally(): void
    {
        $this->sale(['product_name' => '100% Cotton', 'total_amount' => 10]);
        $this->sale(['product_name' => 'Plain shirt', 'total_amount' => 1]);

        $this->assertSame(10.0, $this->sumWith(['field' => 'product_name', 'operator' => 'contains', 'value' => '%']));
        $this->assertNull($this->sumWith(['field' => 'product_name', 'operator' => 'contains', 'value' => '_']));
    }

    public function test_values_are_bound_and_never_interpreted_as_sql(): void
    {
        $this->sale(['region' => 'Norte', 'total_amount' => 10]);

        $injection = "Norte' OR '1'='1";

        $this->assertNull($this->sumWith(['field' => 'region', 'operator' => 'equals', 'value' => $injection]));
        $this->assertDatabaseCount('demo_sales', 1);
    }

    public function test_limit_and_ordering_are_applied(): void
    {
        foreach (['Norte' => 5, 'Sur' => 50, 'Este' => 20, 'Oeste' => 1] as $region => $amount) {
            $this->sale(['region' => $region, 'total_amount' => $amount]);
        }

        $spec = $this->chart(order: [['field' => 'total', 'direction' => 'desc']], limit: 2);

        $this->assertSame(['Sur', 'Este'], array_column($this->execute($spec)['by_region']['rows'], 'region'));
    }

    public function test_an_invalid_stored_spec_is_rejected_before_any_query_runs(): void
    {
        $spec = $this->chart();
        $definition = $spec->toArray();
        $definition['visualizations'][0]['query']['dimensions'][0]['field'] = 'password; DROP TABLE users';

        $this->expectException(InvalidDashboardSpecException::class);
        app(DashboardQueryExecutor::class)->execute(DashboardSpec::fromArray($definition));
    }

    public function test_fields_outside_the_catalog_cannot_be_queried(): void
    {
        $definition = $this->chart()->toArray();
        $definition['visualizations'][0]['query']['dimensions'][0]['field'] = 'tenant_id';

        $this->expectException(InvalidDashboardSpecException::class);
        app(DashboardQueryExecutor::class)->execute(DashboardSpec::fromArray($definition));
    }

    public function test_a_chosen_period_replaces_the_dashboards_own_date_filters(): void
    {
        // Now is 2026-11-12. The spec filters the previous quarter (Jul - Sep).
        $this->sale(['sold_at' => '2026-08-10 10:00:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-11-05 10:00:00', 'total_amount' => 100]);
        $spec = $this->singleKpi([['field' => 'sold_at', 'operator' => 'previous_quarter', 'value' => null]]);

        $this->assertSame(10.0, $this->execute($spec)['total']['rows'][0]['total']);
        $this->assertSame(10.0, $this->execute($spec, DashboardPeriod::Spec)['total']['rows'][0]['total']);
        $this->assertSame(100.0, $this->execute($spec, DashboardPeriod::ThisMonth)['total']['rows'][0]['total']);
        $this->assertSame(110.0, $this->execute($spec, DashboardPeriod::All)['total']['rows'][0]['total']);
    }

    public function test_a_chosen_period_keeps_filters_on_other_fields(): void
    {
        $this->sale(['region' => 'Norte', 'sold_at' => '2026-11-05 10:00:00', 'total_amount' => 100]);
        $this->sale(['region' => 'Sur', 'sold_at' => '2026-11-06 10:00:00', 'total_amount' => 1000]);
        $spec = $this->singleKpi([
            ['field' => 'sold_at', 'operator' => 'previous_quarter', 'value' => null],
            ['field' => 'region', 'operator' => 'equals', 'value' => 'Norte'],
        ]);

        $this->assertSame(100.0, $this->execute($spec, DashboardPeriod::ThisMonth)['total']['rows'][0]['total']);
    }

    public function test_a_date_filter_inside_a_visualization_survives_a_chosen_period(): void
    {
        $this->sale(['sold_at' => '2026-10-10 10:00:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-11-05 10:00:00', 'total_amount' => 100]);
        $kpi = $this->visualization('last_month', 'kpi', metrics: [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total']], dimensions: [], display: ['show_comparison' => false]);
        $kpi['query']['filters'] = [['field' => 'sold_at', 'operator' => 'previous_period', 'value' => null]];

        // "All" removes the global range, but this KPI is deliberately about last month.
        $this->assertSame(10.0, $this->execute($this->spec([$kpi]), DashboardPeriod::All)['last_month']['rows'][0]['total']);
    }

    public function test_the_last_thirty_days_include_today_and_exclude_the_day_before_the_window(): void
    {
        $this->sale(['sold_at' => '2026-10-13 23:59:59', 'total_amount' => 1]);
        $this->sale(['sold_at' => '2026-10-14 00:00:00', 'total_amount' => 10]);
        $this->sale(['sold_at' => '2026-11-12 11:00:00', 'total_amount' => 100]);

        $this->assertSame(110.0, $this->execute($this->singleKpi([]), DashboardPeriod::Last30Days)['total']['rows'][0]['total']);
    }

    public function test_a_period_never_leaks_other_tenants_data(): void
    {
        $this->sale(['sold_at' => '2026-11-05 10:00:00', 'total_amount' => 100]);
        $this->sale(['sold_at' => '2026-11-05 10:00:00', 'total_amount' => 9999], $this->otherTenant);

        $this->assertSame(100.0, $this->execute($this->singleKpi([]), DashboardPeriod::ThisYear)['total']['rows'][0]['total']);
    }

    public function test_results_report_the_month_in_progress(): void
    {
        $this->sale(['total_amount' => 1]);

        $this->assertSame('2026-11', $this->execute($this->singleKpi([]))['total']['meta']['current_month']);
    }

    public function test_execution_requires_a_current_tenant(): void
    {
        app(CurrentTenant::class)->forget();

        $this->expectException(LogicException::class);
        app(DashboardQueryExecutor::class)->execute($this->chart());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sale(array $attributes = [], ?Tenant $tenant = null): DemoSale
    {
        return DemoSale::factory()->for($tenant ?? $this->tenant)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function sumWith(array $filter): ?float
    {
        $rows = $this->execute($this->singleKpi([$filter]))['total']['rows'];

        return $rows[0]['total'] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $globalFilters
     */
    private function singleKpi(array $globalFilters): DashboardSpec
    {
        return $this->spec(
            [$this->visualization('total', 'kpi', metrics: [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total']], dimensions: [], display: ['show_comparison' => false])],
            $globalFilters,
        );
    }

    /**
     * @param  list<array<string, string>>  $order
     */
    private function chart(array $order = [], int $limit = 20): DashboardSpec
    {
        return $this->spec([$this->visualization('by_region', 'bar', metrics: [
            ['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total', 'format' => 'currency'],
        ], dimensions: [['field' => 'region']], display: ['x_axis' => 'region', 'y_axis' => 'total'], order: $order, limit: $limit)]);
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  list<array<string, mixed>>  $dimensions
     * @param  array<string, mixed>  $display
     * @param  list<array<string, string>>  $order
     * @return array<string, mixed>
     */
    private function visualization(string $key, string $type, array $metrics, array $dimensions, array $display, array $order = [], int $limit = 20): array
    {
        return [
            'key' => $key,
            'title' => $key,
            'type' => $type,
            'query' => [
                'metrics' => $metrics,
                'dimensions' => $dimensions,
                'filters' => [],
                'order_by' => $order,
                'limit' => $limit,
            ],
            'display' => $display,
            'layout' => ['x' => 0, 'y' => 0, 'width' => 6, 'height' => 4],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $visualizations
     * @param  list<array<string, mixed>>  $globalFilters
     */
    private function spec(array $visualizations, array $globalFilters = []): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Test',
            'description' => 'Test dashboard',
            'dataset' => 'demo_sales',
            'global_filters' => $globalFilters,
            'visualizations' => $visualizations,
            'layout' => ['columns' => 12],
        ]);
    }

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, meta: array{current_month: string}}>
     */
    private function execute(DashboardSpec $spec, DashboardPeriod $period = DashboardPeriod::Spec): array
    {
        return app(DashboardQueryExecutor::class)->execute($spec, $period);
    }
}
