<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The visualization types added in DashboardSpec 1.2: what each one needs from its query
 * and display, and the bounds that keep it legible.
 */
class DashboardSpecVisualTypesTest extends TestCase
{
    private DashboardSpecValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new DashboardSpecValidator;
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function validVisualizations(): array
    {
        return [
            'hbar' => [self::visualization('hbar', ['x_axis' => 'region', 'y_axis' => 'total'])],
            'funnel' => [self::visualization('funnel', ['category' => 'region', 'value' => 'total'], limit: 8)],
            'treemap' => [self::visualization('treemap', ['category' => 'region', 'value' => 'total'], limit: 20)],
            'radar' => [self::visualization('radar', ['category' => 'region', 'value' => 'total'], limit: 6)],
            'radar at its minimum' => [self::visualization('radar', ['category' => 'region', 'value' => 'total'], limit: 3)],
            'scatter' => [self::visualization(
                'scatter',
                ['x_axis' => 'total', 'y_axis' => 'units', 'category' => 'region'],
                metrics: [self::metric('total', 'sum', 'total_amount'), self::metric('units', 'sum', 'quantity')],
                limit: 100,
            )],
            'gauge' => [self::visualization(
                'gauge',
                ['value' => 'avg_units', 'max' => 10],
                metrics: [self::metric('avg_units', 'avg', 'quantity')],
                dimensions: [],
                limit: 1,
            )],
            'gauge with a decimal maximum' => [self::visualization(
                'gauge',
                ['value' => 'avg_units', 'max' => 4.5],
                metrics: [self::metric('avg_units', 'avg', 'quantity')],
                dimensions: [],
                limit: 1,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $visualization
     */
    #[DataProvider('validVisualizations')]
    public function test_each_new_type_is_accepted_in_version_1_2(array $visualization): void
    {
        $spec = DashboardSpec::fromArray($this->spec($visualization));

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    /**
     * @param  array<string, mixed>  $visualization
     */
    #[DataProvider('validVisualizations')]
    public function test_the_new_types_do_not_exist_before_version_1_2(array $visualization): void
    {
        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('type is not supported');

        $this->validator->validate(DashboardSpec::fromArray($this->spec($visualization, '1.1')));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidVisualizations(): array
    {
        $category = ['category' => 'region', 'value' => 'total'];
        $scatterMetrics = [self::metric('total', 'sum', 'total_amount'), self::metric('units', 'sum', 'quantity')];
        $gaugeMetrics = [self::metric('avg_units', 'avg', 'quantity')];

        return [
            'funnel over 8 stages' => [self::visualization('funnel', $category, limit: 9), 'may not exceed 8 categories for a funnel'],
            'treemap over 20 tiles' => [self::visualization('treemap', $category, limit: 21), 'may not exceed 20 categories for a treemap'],
            'radar over 8 spokes' => [self::visualization('radar', $category, limit: 9), 'may not exceed 8 categories for a radar'],
            'radar under 3 spokes' => [self::visualization('radar', $category, limit: 2), 'at least 3 categories for a radar'],
            'funnel with two dimensions' => [self::visualization('funnel', $category, dimensions: [['field' => 'region'], ['field' => 'team']]), 'exactly one dimension for a funnel'],
            'treemap without a dimension' => [self::visualization('treemap', $category, dimensions: []), 'exactly one dimension for a treemap'],
            'hbar pointing at a missing metric' => [self::visualization('hbar', ['x_axis' => 'region', 'y_axis' => 'nope']), 'y_axis must reference a configured metric alias'],
            'category chart with the wrong display keys' => [self::visualization('funnel', ['x_axis' => 'region', 'y_axis' => 'total']), 'missing required fields'],
            'scatter with one metric' => [self::visualization('scatter', ['x_axis' => 'total', 'y_axis' => 'total', 'category' => 'region']), 'at least two metrics'],
            'scatter over 100 points' => [self::visualization('scatter', ['x_axis' => 'total', 'y_axis' => 'units', 'category' => 'region'], metrics: $scatterMetrics, limit: 101), 'may not exceed 100 points'],
            'scatter with the same metric on both axes' => [self::visualization('scatter', ['x_axis' => 'total', 'y_axis' => 'total', 'category' => 'region'], metrics: $scatterMetrics), 'must be different metrics'],
            'scatter whose axis is a dimension' => [self::visualization('scatter', ['x_axis' => 'region', 'y_axis' => 'units', 'category' => 'region'], metrics: $scatterMetrics), 'x_axis must reference a configured metric alias'],
            'scatter whose label is a metric' => [self::visualization('scatter', ['x_axis' => 'total', 'y_axis' => 'units', 'category' => 'total'], metrics: $scatterMetrics), 'category must reference a configured dimension'],
            'gauge with a dimension' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => 10], metrics: $gaugeMetrics, limit: 1), 'exactly one metric and no dimensions'],
            'gauge with two metrics' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => 10], metrics: [...$gaugeMetrics, self::metric('other', 'sum', 'quantity')], dimensions: []), 'exactly one metric and no dimensions'],
            'gauge with a zero maximum' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => 0], metrics: $gaugeMetrics, dimensions: []), 'max must be a positive number'],
            'gauge with a negative maximum' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => -5], metrics: $gaugeMetrics, dimensions: []), 'max must be a positive number'],
            'gauge with a text maximum' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => '10'], metrics: $gaugeMetrics, dimensions: []), 'max must be a positive number'],
            'gauge with a boolean maximum' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => true], metrics: $gaugeMetrics, dimensions: []), 'max must be a positive number'],
            'gauge with an absurd maximum' => [self::visualization('gauge', ['value' => 'avg_units', 'max' => 1.0e15], metrics: $gaugeMetrics, dimensions: []), 'max must be a positive number'],
            'gauge value that is not a metric' => [self::visualization('gauge', ['value' => 'region', 'max' => 10], metrics: $gaugeMetrics, dimensions: []), 'value must reference a configured metric alias'],
        ];
    }

    /**
     * @param  array<string, mixed>  $visualization
     */
    #[DataProvider('invalidVisualizations')]
    public function test_each_new_type_enforces_its_shape_and_bounds(array $visualization, string $reason): void
    {
        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage($reason);

        $this->validator->validate(DashboardSpec::fromArray($this->spec($visualization)));
    }

    // --- helpers ---------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $visualization
     * @return array<string, mixed>
     */
    private function spec(array $visualization, string $version = '1.2'): array
    {
        return [
            'version' => $version,
            'title' => 'Prueba',
            'description' => '',
            'dataset' => 'demo_sales',
            'global_filters' => [],
            'visualizations' => [$visualization],
            'layout' => ['columns' => 12],
        ];
    }

    /**
     * @param  array<string, mixed>  $display
     * @param  list<array<string, mixed>>|null  $metrics
     * @param  list<array<string, string>>|null  $dimensions
     * @return array<string, mixed>
     */
    private static function visualization(string $type, array $display, ?array $metrics = null, ?array $dimensions = null, int $limit = 6): array
    {
        return [
            'key' => 'chart',
            'title' => 'Gráfica',
            'type' => $type,
            'query' => [
                'metrics' => $metrics ?? [self::metric('total', 'sum', 'total_amount')],
                'dimensions' => $dimensions ?? [['field' => 'region']],
                'filters' => [],
                'order_by' => [],
                'limit' => $limit,
            ],
            'display' => $display,
            'layout' => ['x' => 0, 'y' => 0, 'width' => 6, 'height' => 4],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function metric(string $alias, string $aggregation, string $field): array
    {
        return ['field' => $field, 'aggregation' => $aggregation, 'alias' => $alias];
    }
}
