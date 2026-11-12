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

    private function salesByRegion(): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Ventas por región',
            'description' => 'Definición demostrativa para analizar ventas regionales del trimestre anterior.',
            'dataset' => 'demo_sales',
            'global_filters' => [$this->filter('sold_at', 'previous_quarter', null)],
            'visualizations' => [
                $this->kpi('total_sales', 'Ventas totales', 'total_amount', 'sum', 'total_sales', 'currency'),
                $this->chart('sales_by_region', 'Ventas por región', 'bar', 'region', 'total_amount', 'sum', 'total_sales'),
            ],
            'layout' => ['columns' => 12],
        ]);
    }

    private function revenueComparison(): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Comparación de ingresos',
            'description' => 'Definición demostrativa para comparar la evolución de ingresos por periodo.',
            'dataset' => 'demo_sales',
            'global_filters' => [$this->filter('sold_at', 'previous_period', null)],
            'visualizations' => [
                $this->kpi('total_revenue', 'Ingresos del periodo', 'total_amount', 'sum', 'total_revenue', 'currency'),
                $this->chart('revenue_over_time', 'Evolución de ingresos', 'line', 'sold_at', 'total_amount', 'sum', 'total_revenue'),
            ],
            'layout' => ['columns' => 12],
        ]);
    }

    private function decliningProducts(): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Productos con ventas disminuidas',
            'description' => 'Definición demostrativa para revisar productos con menor volumen en el periodo.',
            'dataset' => 'demo_sales',
            'global_filters' => [$this->filter('sold_at', 'previous_period', null)],
            'visualizations' => [
                $this->table(
                    'products_to_review',
                    'Productos para revisión',
                    ['product_name'],
                    [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total_sales', 'format' => 'currency']],
                ),
            ],
            'layout' => ['columns' => 12],
        ]);
    }

    private function teamPerformance(): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Rendimiento del equipo',
            'description' => 'Definición demostrativa para comparar ventas agregadas por integrante.',
            'dataset' => 'demo_sales',
            'global_filters' => [],
            'visualizations' => [
                $this->chart('sales_by_representative', 'Ventas por integrante', 'bar', 'salesperson', 'total_amount', 'sum', 'total_sales'),
            ],
            'layout' => ['columns' => 12],
        ]);
    }

    private function genericDemo(): DashboardSpec
    {
        return DashboardSpec::fromArray([
            'version' => '1.0',
            'title' => 'Dashboard de demostración',
            'description' => 'Definición genérica creada por el proveedor fake; no representa una consulta ejecutada.',
            'dataset' => 'demo_sales',
            'global_filters' => [],
            'visualizations' => [
                $this->table(
                    'sales_summary',
                    'Resumen estructurado de ventas',
                    ['region'],
                    [['field' => 'total_amount', 'aggregation' => 'sum', 'alias' => 'total_sales', 'format' => 'currency']],
                ),
            ],
            'layout' => ['columns' => 12],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $key, string $title, string $field, string $aggregation, string $alias, string $format): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'type' => 'kpi',
            'query' => [
                'metrics' => [['field' => $field, 'aggregation' => $aggregation, 'alias' => $alias, 'format' => $format]],
                'dimensions' => [],
                'filters' => [],
                'order_by' => [],
                'limit' => 1,
            ],
            'display' => ['show_comparison' => false],
            'layout' => ['x' => 0, 'y' => 0, 'width' => 4, 'height' => 2],
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
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'query' => [
                'metrics' => [['field' => $metric, 'aggregation' => $aggregation, 'alias' => $alias, 'format' => 'currency']],
                'dimensions' => [['field' => $dimension]],
                'filters' => [],
                'order_by' => [['field' => $alias, 'direction' => 'desc']],
                'limit' => 20,
            ],
            'display' => ['x_axis' => $dimension, 'y_axis' => $alias],
            'layout' => ['x' => 0, 'y' => 2, 'width' => 8, 'height' => 4],
        ];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private function table(string $key, string $title, array $dimensions, array $metrics): array
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
            'layout' => ['x' => 0, 'y' => 0, 'width' => 12, 'height' => 4],
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
