<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use Illuminate\Support\Str;

final class FakeDashboardGenerator implements DashboardGenerator
{
    public function provider(): string
    {
        return 'fake';
    }

    public function generate(DashboardGenerationInput $input): DashboardSpec
    {
        if ($input->isRefinement()) {
            return $this->refine($input);
        }

        $prompt = Str::lower(Str::ascii($input->prompt));

        if (str_contains($prompt, 'ventas por region')) {
            return $this->salesByRegion();
        }

        if (str_contains($prompt, 'comparar ingresos') || str_contains($prompt, 'comparacion de ingresos')) {
            return $this->revenueComparison();
        }

        if (str_contains($prompt, 'productos') && (str_contains($prompt, 'disminu') || str_contains($prompt, 'menor'))) {
            return $this->decliningProducts();
        }

        if (str_contains($prompt, 'rendimiento del equipo') || str_contains($prompt, 'desempeno del equipo')) {
            return $this->teamPerformance();
        }

        return $this->genericDemo();
    }

    /**
     * Deterministic stand-in for an AI edit: understands a few phrases (change a
     * chart type, add a detail table, remove filters) and otherwise returns the
     * spec unchanged, like a model that cannot do what was asked.
     */
    private function refine(DashboardGenerationInput $input): DashboardSpec
    {
        $spec = $input->currentSpec ?? [];
        $request = Str::lower(Str::ascii($input->prompt));

        if (preg_match('/\b(quita|elimina|borra|sin)\b.*\bfiltros?\b/', $request) === 1) {
            $spec['global_filters'] = [];
        }

        $type = $this->requestedChartType($request);

        if ($type !== null) {
            foreach ($spec['visualizations'] as $index => $visualization) {
                if (in_array($visualization['type'], ['bar', 'line', 'area', 'pie'], true)) {
                    $spec['visualizations'][$index] = $this->convertChart($visualization, $type);

                    break;
                }
            }
        }

        if (str_contains($request, 'tabla') && preg_match('/\b(agrega|anade|incluye|con)\b/', $request) === 1) {
            $spec = $this->withDetailTable($spec);
        }

        $spec['version'] = '1.1';

        return DashboardSpec::fromArray($spec);
    }

    /**
     * The type whose keyword appears last wins: "cambia las barras por un gráfico circular" means pie.
     */
    private function requestedChartType(string $request): ?string
    {
        $keywords = [
            'pie' => ['circular', 'pastel', 'pie', 'dona', 'donut'],
            'bar' => ['barras', 'columnas'],
            'line' => ['linea'],
            'area' => ['area'],
        ];
        $found = null;
        $latest = -1;

        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                $position = strrpos($request, $word);

                if ($position !== false && $position > $latest) {
                    $latest = $position;
                    $found = $type;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $visualization
     * @return array<string, mixed>
     */
    private function convertChart(array $visualization, string $type): array
    {
        $dimension = $visualization['query']['dimensions'][0]['field'] ?? null;
        $metric = $visualization['query']['metrics'][0] ?? null;

        if ($dimension === null || $metric === null) {
            return $visualization;
        }

        $visualization['type'] = $type;
        $visualization['query']['dimensions'] = [['field' => $dimension]];
        $visualization['query']['metrics'] = [$metric];
        $visualization['query']['order_by'] = array_values(array_filter(
            $visualization['query']['order_by'],
            fn (array $order): bool => in_array($order['field'], [$dimension, $metric['alias']], true),
        ));
        $visualization['display'] = $type === 'pie'
            ? ['category' => $dimension, 'value' => $metric['alias']]
            : ['x_axis' => $dimension, 'y_axis' => $metric['alias']];

        if ($type === 'pie') {
            $visualization['query']['limit'] = min(6, (int) $visualization['query']['limit']);
        }

        return $visualization;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function withDetailTable(array $spec): array
    {
        $keys = array_column($spec['visualizations'], 'key');
        $source = collect($spec['visualizations'])->first(fn (array $visualization): bool => $visualization['query']['dimensions'] !== []);

        if ($source === null || in_array('detail_table', $keys, true) || count($keys) >= 12) {
            return $spec;
        }

        $bottom = collect($spec['visualizations'])->max(fn (array $visualization): int => $visualization['layout']['y'] + $visualization['layout']['height']);
        $dimensions = array_column($source['query']['dimensions'], 'field');
        $metrics = $source['query']['metrics'];

        $table = $this->table('detail_table', 'Detalle', $dimensions, $metrics, 0, (int) $bottom);
        $table['query']['filters'] = $source['query']['filters'];

        $spec['visualizations'][] = $table;

        return $spec;
    }

    private function salesByRegion(): DashboardSpec
    {
        return $this->spec('Ventas por región', 'Ventas del trimestre anterior, su volumen y cómo se reparten entre regiones.', [
            $this->filter('sold_at', 'previous_quarter', null),
        ], [
            $this->kpi('total_sales', 'Ventas totales', 'total_amount', 'sum', 'total_sales', 'currency', 0, 0),
            $this->kpi('orders', 'Pedidos', 'product_id', 'count', 'orders', 'integer', 4, 0),
            $this->kpi('avg_ticket', 'Ticket promedio', 'total_amount', 'avg', 'avg_ticket', 'currency', 8, 0),
            $this->chart('sales_by_region', 'Ventas por región', 'bar', 'region', 'total_amount', 'sum', 'total_sales', 0, 2, 7, 5),
            $this->pie('region_share', 'Participación por región', 'region', 'total_amount', 'sum', 'total_sales', 7, 2, 5, 5),
        ]);
    }

    private function revenueComparison(): DashboardSpec
    {
        return $this->spec('Comparación de ingresos', 'Evolución mensual de ingresos frente al último mes completo.', [], [
            $this->kpi('last_month_revenue', 'Ingresos del mes anterior', 'total_amount', 'sum', 'last_month_revenue', 'currency', 0, 0, [
                $this->filter('sold_at', 'previous_period', null),
            ]),
            $this->kpi('year_revenue', 'Ingresos del año', 'total_amount', 'sum', 'year_revenue', 'currency', 4, 0),
            $this->kpi('units', 'Unidades vendidas', 'quantity', 'sum', 'units', 'integer', 8, 0),
            $this->chart('revenue_over_time', 'Evolución mensual de ingresos', 'area', 'sold_at', 'total_amount', 'sum', 'total_revenue', 0, 2, 12, 5),
        ]);
    }

    private function decliningProducts(): DashboardSpec
    {
        return $this->spec('Productos con ventas disminuidas', 'Ventas por producto, de menor a mayor, para detectar los que pierden tracción.', [
            $this->filter('sold_at', 'previous_quarter', null),
        ], [
            $this->chart('lowest_products', 'Productos con menores ventas', 'bar', 'product_name', 'total_amount', 'sum', 'total_sales', 0, 0, 12, 5, 'asc'),
            $this->table(
                'products_to_review',
                'Productos para revisión',
                ['product_name'],
                [
                    ['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total_sales', 'format' => 'currency'],
                    ['field' => 'quantity', 'aggregation' => 'sum', 'alias' => 'units', 'format' => 'integer'],
                ],
                0,
                5,
            ),
        ]);
    }

    private function teamPerformance(): DashboardSpec
    {
        return $this->spec('Rendimiento del equipo', 'Ventas por integrante y por equipo comercial.', [], [
            $this->chart('sales_by_representative', 'Ventas por integrante', 'bar', 'salesperson', 'total_amount', 'sum', 'total_sales', 0, 0, 7, 5),
            $this->pie('sales_by_team', 'Ventas por equipo', 'team', 'total_amount', 'sum', 'total_sales', 7, 0, 5, 5),
        ]);
    }

    private function genericDemo(): DashboardSpec
    {
        return $this->spec('Dashboard de demostración', 'Un resumen general de ventas por región, con los datos de ejemplo de tu organización.', [], [
            $this->kpi('total_sales', 'Ventas totales', 'total_amount', 'sum', 'total_sales', 'currency', 0, 0),
            $this->kpi('orders', 'Pedidos', 'product_id', 'count', 'orders', 'integer', 4, 0),
            $this->table(
                'sales_summary',
                'Resumen de ventas por región',
                ['region'],
                [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total_sales', 'format' => 'currency']],
                0,
                2,
            ),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $globalFilters
     * @param  list<array<string, mixed>>  $visualizations
     */
    private function spec(string $title, string $description, array $globalFilters, array $visualizations): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.1',
            'title' => $title,
            'description' => $description,
            'dataset' => 'demo_sales',
            'global_filters' => $globalFilters,
            'visualizations' => $visualizations,
            'layout' => ['columns' => 12],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @return array<string, mixed>
     */
    private function kpi(string $key, string $title, string $field, string $aggregation, string $alias, string $format, int $x, int $y, array $filters = []): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'type' => 'kpi',
            'query' => [
                'metrics' => [['field' => $field, 'aggregation' => $aggregation, 'alias' => $alias, 'format' => $format]],
                'dimensions' => [],
                'filters' => $filters,
                'order_by' => [],
                'limit' => 1,
            ],
            'display' => ['show_comparison' => false],
            'layout' => ['x' => $x, 'y' => $y, 'width' => 4, 'height' => 2],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chart(
        string $key,
        string $title,
        string $type,
        string $dimension,
        string $metric,
        string $aggregation,
        string $alias,
        int $x,
        int $y,
        int $width,
        int $height,
        string $direction = 'desc',
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'query' => [
                'metrics' => [['field' => $metric, 'aggregation' => $aggregation, 'alias' => $alias, 'format' => 'currency']],
                'dimensions' => [['field' => $dimension]],
                'filters' => [],
                'order_by' => $type === 'bar' ? [['field' => $alias, 'direction' => $direction]] : [],
                'limit' => $type === 'bar' ? 8 : 24,
            ],
            'display' => ['x_axis' => $dimension, 'y_axis' => $alias],
            'layout' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pie(
        string $key,
        string $title,
        string $dimension,
        string $metric,
        string $aggregation,
        string $alias,
        int $x,
        int $y,
        int $width,
        int $height,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'type' => 'pie',
            'query' => [
                'metrics' => [['field' => $metric, 'aggregation' => $aggregation, 'alias' => $alias, 'format' => 'currency']],
                'dimensions' => [['field' => $dimension]],
                'filters' => [],
                'order_by' => [['field' => $alias, 'direction' => 'desc']],
                'limit' => 6,
            ],
            'display' => ['category' => $dimension, 'value' => $alias],
            'layout' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
        ];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private function table(string $key, string $title, array $dimensions, array $metrics, int $x, int $y): array
    {
        $columns = [...$dimensions, ...array_column($metrics, 'alias')];

        return [
            'key' => $key,
            'title' => $title,
            'type' => 'table',
            'query' => [
                'metrics' => $metrics,
                'dimensions' => array_map(fn (string $field): array => ['field' => $field], $dimensions),
                'filters' => [],
                'order_by' => [['field' => $metrics[0]['alias'], 'direction' => 'asc']],
                'limit' => 50,
            ],
            'display' => ['columns' => $columns],
            'layout' => ['x' => $x, 'y' => $y, 'width' => 12, 'height' => 5],
        ];
    }

    /**
     * @return array{field: string, operator: string, value: mixed}
     */
    private function filter(string $field, string $operator, mixed $value): array
    {
        return ['field' => $field, 'operator' => $operator, 'value' => $value];
    }
}
