<?php

namespace App\Domain\Dashboards\Queries;

use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardPeriod;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Runs the structured queries of a DashboardSpec against the demo dataset.
 *
 * No SQL ever comes from the spec or the AI. Every identifier that reaches the
 * query is taken from the approved catalog (a constant allowlist) or is an
 * already-validated metric alias, aggregations and operators map to fixed
 * strings, and every value is bound as a parameter. The data is always
 * restricted to the current tenant, resolved from CurrentTenant.
 */
final class DashboardQueryExecutor
{
    private const AGGREGATIONS = [
        'sum' => 'SUM',
        'avg' => 'AVG',
        'count' => 'COUNT',
        'min' => 'MIN',
        'max' => 'MAX',
    ];

    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly DashboardSpecValidator $validator,
        private readonly DemoSalesDatasetCatalog $catalog,
    ) {}

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, meta: array{current_month: string}}>
     */
    public function execute(DashboardSpec $spec, DashboardPeriod $period = DashboardPeriod::Spec): array
    {
        // The stored definition is untrusted until proven otherwise.
        $definition = $this->validator->validate($spec)->toArray();
        $tenant = $this->currentTenant->require();
        $now = CarbonImmutable::now();
        $range = $period->range($now);

        // A chosen period replaces the dashboard's own date filters; filters on
        // other fields, and date filters inside a single visualization, stay.
        $globalFilters = $period->overridesSpec()
            ? $this->withoutDateFilters($definition['global_filters'])
            : $definition['global_filters'];
        $results = [];

        foreach ($definition['visualizations'] as $visualization) {
            $result = $this->run($tenant, $visualization['query'], $globalFilters, $range);
            // The month still in progress is incomplete; the UI marks it as such.
            $result['meta'] = ['current_month' => $now->format('Y-m')];
            $results[$visualization['key']] = $result;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<array<string, mixed>>  $globalFilters
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $range
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function run(Tenant $tenant, array $query, array $globalFilters, ?array $range = null): array
    {
        $builder = DemoSale::query()->forTenant($tenant);

        foreach ([...$globalFilters, ...$query['filters']] as $filter) {
            $this->applyFilter($builder, $filter);
        }

        if ($range !== null) {
            $this->whereHalfOpen($builder, $this->catalog->dateField(), $range[0], $range[1]);
        }

        $grammar = $builder->getQuery()->getGrammar();
        $selects = [];
        $groups = [];
        $dimensionExpressions = [];
        $columns = [];

        foreach ($query['dimensions'] as $dimension) {
            $field = $dimension['field'];
            $expression = $this->dimensionExpression($builder, $field);
            $dimensionExpressions[$field] = $expression;
            $selects[] = $expression.' as '.$grammar->wrap($field);
            $groups[] = $expression;
            $columns[] = [
                'key' => $field,
                'role' => 'dimension',
                'type' => $this->catalog->typeOf($field) === 'datetime' ? 'month' : $this->catalog->typeOf($field),
                'format' => null,
            ];
        }

        foreach ($query['metrics'] as $metric) {
            $selects[] = self::AGGREGATIONS[$metric['aggregation']]
                .'('.$grammar->wrap($this->column($metric['field'])).') as '.$grammar->wrap($metric['alias']);
            $columns[] = [
                'key' => $metric['alias'],
                'role' => 'metric',
                'type' => 'number',
                'format' => $metric['format'] ?? null,
            ];
        }

        $builder->selectRaw(implode(', ', $selects));

        if ($groups !== []) {
            $builder->groupByRaw(implode(', ', $groups));
        }

        $this->applyOrdering($builder, $query, $dimensionExpressions);
        $builder->limit((int) $query['limit']);

        $metricsByAlias = [];

        foreach ($query['metrics'] as $metric) {
            $metricsByAlias[$metric['alias']] = $metric;
        }

        $rows = $builder->toBase()->get()
            ->map(fn (object $row): array => $this->normalizeRow((array) $row, $metricsByAlias))
            ->values()
            ->all();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @return list<array<string, mixed>>
     */
    private function withoutDateFilters(array $filters): array
    {
        return array_values(array_filter(
            $filters,
            fn (array $filter): bool => $this->catalog->typeOf($filter['field']) !== 'datetime',
        ));
    }

    /**
     * @param  Builder<DemoSale>  $builder
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $dimensionExpressions
     */
    private function applyOrdering(Builder $builder, array $query, array $dimensionExpressions): void
    {
        $grammar = $builder->getQuery()->getGrammar();

        foreach ($query['order_by'] as $order) {
            $target = $dimensionExpressions[$order['field']] ?? $grammar->wrap($order['field']);
            $builder->orderByRaw($target.($order['direction'] === 'desc' ? ' desc' : ' asc'));
        }

        // Trends read left to right: default to chronological order.
        if ($query['order_by'] === []) {
            foreach ($query['dimensions'] as $dimension) {
                if ($this->catalog->typeOf($dimension['field']) === 'datetime') {
                    $builder->orderByRaw($dimensionExpressions[$dimension['field']].' asc');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $metricsByAlias
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, array $metricsByAlias): array
    {
        foreach ($metricsByAlias as $alias => $metric) {
            $value = $row[$alias] ?? null;

            if ($value === null) {
                continue;
            }

            $isNumeric = $this->catalog->typeOf($metric['field']) === 'numeric';

            $row[$alias] = match (true) {
                $metric['aggregation'] === 'count' => (int) $value,
                $metric['aggregation'] === 'sum', $metric['aggregation'] === 'avg', $isNumeric => round((float) $value, 2),
                default => $value,
            };
        }

        return $row;
    }

    /**
     * Resolves a field name to its column using only the catalog allowlist.
     */
    private function column(string $field): string
    {
        if (! $this->catalog->hasField($field)) {
            throw new LogicException('Field is not part of the approved dataset catalog.');
        }

        return $field;
    }

    /**
     * @param  Builder<DemoSale>  $builder
     */
    private function dimensionExpression(Builder $builder, string $field): string
    {
        $wrapped = $builder->getQuery()->getGrammar()->wrap($this->column($field));

        if ($this->catalog->typeOf($field) !== 'datetime') {
            return $wrapped;
        }

        // Datetime dimensions are bucketed by month. Expressions are constants
        // per driver; nothing user-controlled is interpolated.
        return match ($builder->getConnection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$wrapped})",
            'pgsql' => "to_char({$wrapped}, 'YYYY-MM')",
            default => throw new LogicException('Month bucketing is not supported by this database driver.'),
        };
    }

    /**
     * @param  Builder<DemoSale>  $builder
     * @param  array<string, mixed>  $filter
     */
    private function applyFilter(Builder $builder, array $filter): void
    {
        $column = $this->column($filter['field']);
        $type = (string) $this->catalog->typeOf($column);
        $value = $filter['value'];

        switch ($filter['operator']) {
            case 'equals':
            case 'not_equals':
                $this->applyEquality($builder, $column, $type, $value, $filter['operator'] === 'not_equals');

                return;

            case 'in':
                $builder->whereIn($column, $this->normalizeList($type, $value));

                return;

            case 'not_in':
                $builder->whereNotIn($column, $this->normalizeList($type, $value));

                return;

            case 'greater_than':
                $builder->where($column, '>', $this->scalar($type, $value, end: true));

                return;

            case 'greater_than_or_equal':
                $builder->where($column, '>=', $this->scalar($type, $value, end: false));

                return;

            case 'less_than':
                $builder->where($column, '<', $this->scalar($type, $value, end: false));

                return;

            case 'less_than_or_equal':
                $builder->where($column, '<=', $this->scalar($type, $value, end: true));

                return;

            case 'between':
                $builder->whereBetween($column, [
                    $this->scalar($type, $value[0], end: false),
                    $this->scalar($type, $value[1], end: true),
                ]);

                return;

            case 'contains':
                $wrapped = $builder->getQuery()->getGrammar()->wrap($column);
                $needle = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower((string) $value));
                $builder->whereRaw("LOWER({$wrapped}) LIKE ? ESCAPE '!'", ["%{$needle}%"]);

                return;

            case 'is_null':
                $builder->whereNull($column);

                return;

            case 'is_not_null':
                $builder->whereNotNull($column);

                return;

            case 'previous_quarter':
                $quarterStart = CarbonImmutable::now()->startOfQuarter();
                $this->whereHalfOpen($builder, $column, $quarterStart->subQuarterNoOverflow(), $quarterStart);

                return;

            case 'previous_period':
                // Defined as the previous calendar month.
                $monthStart = CarbonImmutable::now()->startOfMonth();
                $this->whereHalfOpen($builder, $column, $monthStart->subMonthNoOverflow(), $monthStart);

                return;
        }

        throw new LogicException('Unsupported filter operator.');
    }

    /**
     * @param  Builder<DemoSale>  $builder
     */
    private function whereHalfOpen(Builder $builder, string $column, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $builder->where($column, '>=', $this->format($from))->where($column, '<', $this->format($to));
    }

    /**
     * @param  Builder<DemoSale>  $builder
     */
    private function applyEquality(Builder $builder, string $column, string $type, mixed $value, bool $negate): void
    {
        if ($type === 'datetime' && is_string($value) && $this->isDateOnly($value)) {
            $range = [$this->scalar($type, $value, end: false), $this->scalar($type, $value, end: true)];
            $negate ? $builder->whereNotBetween($column, $range) : $builder->whereBetween($column, $range);

            return;
        }

        $builder->where($column, $negate ? '!=' : '=', $this->scalar($type, $value, end: false));
    }

    /**
     * @param  list<mixed>  $values
     * @return list<mixed>
     */
    private function normalizeList(string $type, array $values): array
    {
        return array_map(fn (mixed $value): mixed => $this->scalar($type, $value, end: false), $values);
    }

    /**
     * Datetime strings become UTC-agnostic "Y-m-d H:i:s" values; date-only
     * strings expand to the start or end of that day depending on the bound.
     */
    private function scalar(string $type, mixed $value, bool $end): mixed
    {
        if ($type !== 'datetime' || ! is_string($value)) {
            return $value;
        }

        $moment = CarbonImmutable::parse($value);

        if ($this->isDateOnly($value)) {
            $moment = $end ? $moment->endOfDay() : $moment->startOfDay();
        }

        return $this->format($moment);
    }

    private function isDateOnly(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function format(CarbonImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }
}
