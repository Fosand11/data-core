<?php

namespace App\Domain\Ai\Validation;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;

final class DashboardSpecValidator
{
    private const AGGREGATIONS = ['sum', 'avg', 'count', 'min', 'max'];

    private const FILTER_OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'greater_than',
        'greater_than_or_equal',
        'less_than',
        'less_than_or_equal',
        'between',
        'contains',
        'is_null',
        'is_not_null',
        'previous_quarter',
        'previous_period',
    ];

    private const VISUALIZATION_TYPES = ['kpi', 'bar', 'line', 'table'];

    private const FORMATS = ['currency', 'number', 'percent', 'integer'];

    public function validate(DashboardSpec $spec): DashboardSpec
    {
        $definition = $spec->toArray();

        $this->rejectExecutableContent($definition);
        $this->assertObject($definition, '$');
        $this->assertKeys(
            $definition,
            ['version', 'title', 'description', 'dataset', 'global_filters', 'visualizations', 'layout'],
            [],
            '$',
        );

        if ($definition['version'] !== '1.0') {
            $this->fail('$.version must be the supported version "1.0".');
        }

        $this->assertText($definition['title'], '$.title', 1, 120);
        $this->assertText($definition['description'], '$.description', 0, 500);
        $this->assertIdentifier($definition['dataset'], '$.dataset', 64, false);
        $this->validateFilters($definition['global_filters'], '$.global_filters');
        $columns = $this->validateRootLayout($definition['layout']);

        $visualizations = $this->assertList($definition['visualizations'], '$.visualizations');

        if (count($visualizations) < 1 || count($visualizations) > 12) {
            $this->fail('$.visualizations must contain between 1 and 12 items.');
        }

        $keys = [];

        foreach ($visualizations as $index => $visualization) {
            $path = "$.visualizations.{$index}";
            $item = $this->assertObject($visualization, $path);
            $this->assertKeys($item, ['key', 'title', 'type', 'query', 'display', 'layout'], [], $path);
            $this->assertIdentifier($item['key'], "{$path}.key", 64, false);
            $this->assertText($item['title'], "{$path}.title", 1, 120);

            if (in_array($item['key'], $keys, true)) {
                $this->fail("{$path}.key must be unique.");
            }

            $keys[] = $item['key'];

            if (! is_string($item['type']) || ! in_array($item['type'], self::VISUALIZATION_TYPES, true)) {
                $this->fail("{$path}.type is not supported.");
            }

            $queryFields = $this->validateQuery($item['query'], "{$path}.query");
            $this->validateDisplay($item['display'], $item['type'], $queryFields, "{$path}.display");
            $this->validateVisualizationLayout($item['layout'], $columns, "{$path}.layout");
        }

        return $spec;
    }

    /**
     * @return array{metrics: list<string>, dimensions: list<string>}
     */
    private function validateQuery(mixed $query, string $path): array
    {
        $query = $this->assertObject($query, $path);
        $this->assertKeys($query, ['metrics', 'dimensions', 'filters', 'order_by', 'limit'], [], $path);

        $metrics = $this->assertList($query['metrics'], "{$path}.metrics");

        if (count($metrics) < 1 || count($metrics) > 8) {
            $this->fail("{$path}.metrics must contain between 1 and 8 items.");
        }

        $metricAliases = [];

        foreach ($metrics as $index => $metric) {
            $metricPath = "{$path}.metrics.{$index}";
            $metric = $this->assertObject($metric, $metricPath);
            $this->assertKeys($metric, ['field', 'aggregation', 'alias'], ['format'], $metricPath);
            $this->assertIdentifier($metric['field'], "{$metricPath}.field", 128);
            $this->assertIdentifier($metric['alias'], "{$metricPath}.alias", 64, false);

            if (! is_string($metric['aggregation']) || ! in_array($metric['aggregation'], self::AGGREGATIONS, true)) {
                $this->fail("{$metricPath}.aggregation is not supported.");
            }

            if (array_key_exists('format', $metric)) {
                if (! is_string($metric['format']) || ! in_array($metric['format'], self::FORMATS, true)) {
                    $this->fail("{$metricPath}.format is not supported.");
                }
            }

            if (in_array($metric['alias'], $metricAliases, true)) {
                $this->fail("{$metricPath}.alias must be unique within the visualization.");
            }

            $metricAliases[] = $metric['alias'];
        }

        $dimensions = $this->assertList($query['dimensions'], "{$path}.dimensions");

        if (count($dimensions) > 4) {
            $this->fail("{$path}.dimensions may not contain more than 4 items.");
        }

        $dimensionFields = [];

        foreach ($dimensions as $index => $dimension) {
            $dimensionPath = "{$path}.dimensions.{$index}";
            $dimension = $this->assertObject($dimension, $dimensionPath);
            $this->assertKeys($dimension, ['field'], [], $dimensionPath);
            $this->assertIdentifier($dimension['field'], "{$dimensionPath}.field", 128);
            $dimensionFields[] = $dimension['field'];
        }

        $this->validateFilters($query['filters'], "{$path}.filters");
        $orderBy = $this->assertList($query['order_by'], "{$path}.order_by");

        if (count($orderBy) > 5) {
            $this->fail("{$path}.order_by may not contain more than 5 items.");
        }

        foreach ($orderBy as $index => $order) {
            $orderPath = "{$path}.order_by.{$index}";
            $order = $this->assertObject($order, $orderPath);
            $this->assertKeys($order, ['field', 'direction'], [], $orderPath);
            $this->assertIdentifier($order['field'], "{$orderPath}.field", 128);

            if (! is_string($order['direction']) || ! in_array($order['direction'], ['asc', 'desc'], true)) {
                $this->fail("{$orderPath}.direction must be asc or desc.");
            }
        }

        $this->assertIntegerBetween($query['limit'], "{$path}.limit", 1, 500);

        return ['metrics' => $metricAliases, 'dimensions' => $dimensionFields];
    }

    private function validateFilters(mixed $filters, string $path): void
    {
        $filters = $this->assertList($filters, $path);

        if (count($filters) > 10) {
            $this->fail("{$path} may not contain more than 10 items.");
        }

        foreach ($filters as $index => $filter) {
            $filterPath = "{$path}.{$index}";
            $filter = $this->assertObject($filter, $filterPath);
            $this->assertKeys($filter, ['field', 'operator', 'value'], [], $filterPath);
            $this->assertIdentifier($filter['field'], "{$filterPath}.field", 128);

            if (! is_string($filter['operator']) || ! in_array($filter['operator'], self::FILTER_OPERATORS, true)) {
                $this->fail("{$filterPath}.operator is not supported.");
            }

            $this->validateFilterValue($filter['value'], "{$filterPath}.value");
        }
    }

    private function validateFilterValue(mixed $value, string $path): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return;
        }

        if (is_string($value)) {
            $this->assertText($value, $path, 0, 200);

            return;
        }

        if (! is_array($value) || ! array_is_list($value) || count($value) > 50) {
            $this->fail("{$path} must be a scalar, null, or a list of up to 50 scalar values.");
        }

        foreach ($value as $index => $item) {
            if (! is_string($item) && ! is_int($item) && ! is_float($item) && ! is_bool($item) && $item !== null) {
                $this->fail("{$path}.{$index} must be a scalar or null.");
            }

            if (is_string($item)) {
                $this->assertText($item, "{$path}.{$index}", 0, 200);
            }
        }
    }

    /**
     * @param  array{metrics: list<string>, dimensions: list<string>}  $queryFields
     */
    private function validateDisplay(mixed $display, string $type, array $queryFields, string $path): void
    {
        $display = $this->assertObject($display, $path);

        if ($type === 'kpi') {
            $this->assertKeys($display, ['show_comparison'], [], $path);

            if (! is_bool($display['show_comparison'])) {
                $this->fail("{$path}.show_comparison must be a boolean.");
            }

            return;
        }

        if ($type === 'bar' || $type === 'line') {
            $this->assertKeys($display, ['x_axis', 'y_axis'], [], $path);
            $this->assertIdentifier($display['x_axis'], "{$path}.x_axis", 128);
            $this->assertIdentifier($display['y_axis'], "{$path}.y_axis", 128);

            if (! in_array($display['x_axis'], $queryFields['dimensions'], true)) {
                $this->fail("{$path}.x_axis must reference a configured dimension.");
            }

            if (! in_array($display['y_axis'], $queryFields['metrics'], true)) {
                $this->fail("{$path}.y_axis must reference a configured metric alias.");
            }

            return;
        }

        $this->assertKeys($display, ['columns'], [], $path);
        $columns = $this->assertList($display['columns'], "{$path}.columns");
        $available = [...$queryFields['dimensions'], ...$queryFields['metrics']];

        if ($columns === [] || count($columns) > 12) {
            $this->fail("{$path}.columns must contain between 1 and 12 items.");
        }

        foreach ($columns as $index => $column) {
            $this->assertIdentifier($column, "{$path}.columns.{$index}", 128);

            if (! in_array($column, $available, true)) {
                $this->fail("{$path}.columns.{$index} must reference a configured field or metric alias.");
            }
        }
    }

    private function validateRootLayout(mixed $layout): int
    {
        $layout = $this->assertObject($layout, '$.layout');
        $this->assertKeys($layout, ['columns'], [], '$.layout');
        $this->assertIntegerBetween($layout['columns'], '$.layout.columns', 12, 12);

        return $layout['columns'];
    }

    private function validateVisualizationLayout(mixed $layout, int $columns, string $path): void
    {
        $layout = $this->assertObject($layout, $path);
        $this->assertKeys($layout, ['x', 'y', 'width', 'height'], [], $path);
        $this->assertIntegerBetween($layout['x'], "{$path}.x", 0, $columns - 1);
        $this->assertIntegerBetween($layout['y'], "{$path}.y", 0, 100);
        $this->assertIntegerBetween($layout['width'], "{$path}.width", 1, $columns);
        $this->assertIntegerBetween($layout['height'], "{$path}.height", 1, 12);

        if ($columns < $layout['x'] + $layout['width']) {
            $this->fail("{$path} exceeds the dashboard grid width.");
        }
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $optional
     */
    private function assertKeys(array $value, array $required, array $optional, string $path): void
    {
        $keys = array_keys($value);
        $missing = array_diff($required, $keys);
        $unexpected = array_diff($keys, [...$required, ...$optional]);

        if ($missing !== []) {
            $this->fail("{$path} is missing required fields: ".implode(', ', $missing).'.');
        }

        if ($unexpected !== []) {
            $this->fail("{$path} contains unexpected fields: ".implode(', ', $unexpected).'.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertObject(mixed $value, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->fail("{$path} must be an object.");
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function assertList(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->fail("{$path} must be a list.");
        }

        return $value;
    }

    private function assertText(mixed $value, string $path, int $min, int $max): void
    {
        if (! is_string($value)) {
            $this->fail("{$path} must be a string.");
        }

        $length = mb_strlen($value);

        if ($length < $min || $length > $max) {
            $this->fail("{$path} must contain between {$min} and {$max} characters.");
        }
    }

    private function assertIdentifier(mixed $value, string $path, int $max, bool $allowDots = true): void
    {
        $this->assertText($value, $path, 1, $max);
        $pattern = $allowDots ? '/^[A-Za-z][A-Za-z0-9_.]*$/' : '/^[A-Za-z][A-Za-z0-9_]*$/';

        if (preg_match($pattern, $value) !== 1) {
            $this->fail("{$path} must be a simple identifier, not an expression.");
        }
    }

    private function assertIntegerBetween(mixed $value, string $path, int $min, int $max): void
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->fail("{$path} must be an integer between {$min} and {$max}.");
        }
    }

    private function rejectExecutableContent(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), ['sql', 'raw_sql', 'statement'], true)) {
                    $this->fail('DashboardSpec may not contain SQL fields.');
                }

                $this->rejectExecutableContent($item);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        $sqlPattern = '/\b(?:select\s+.+\s+from|insert\s+into|update\s+\S+\s+set|delete\s+from|drop\s+(?:table|database)|alter\s+table|create\s+(?:table|view)|truncate\s+table|with\s+\w+\s+as\s*\()/is';

        if (preg_match($sqlPattern, $value) === 1) {
            $this->fail('DashboardSpec may not contain SQL statements.');
        }

        if (preg_match('/<\/?[A-Za-z][^>]*>/', $value) === 1) {
            $this->fail('DashboardSpec may not contain HTML markup.');
        }
    }

    private function fail(string $message): never
    {
        throw new InvalidDashboardSpecException($message);
    }
}
