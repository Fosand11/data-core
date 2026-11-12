<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DashboardSpecValidatorTest extends TestCase
{
    private DashboardSpecValidator $validator;

    /** @var array<string, mixed> */
    private array $validSpec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new DashboardSpecValidator;
        $this->validSpec = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput('tenant-id', 1, 'ventas por región'))
            ->toArray();
    }

    public function test_valid_spec_is_accepted(): void
    {
        $spec = DashboardSpec::fromArray($this->validSpec);

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    public function test_duplicate_visualization_keys_are_rejected(): void
    {
        $this->validSpec['visualizations'][1]['key'] = $this->validSpec['visualizations'][0]['key'];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_unsupported_visualization_type_is_rejected(): void
    {
        $this->validSpec['visualizations'][0]['type'] = 'pie';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_unsupported_aggregation_is_rejected(): void
    {
        $this->validSpec['visualizations'][0]['query']['metrics'][0]['aggregation'] = 'median';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_sql_content_is_rejected(): void
    {
        $this->validSpec['description'] = 'SELECT total_amount FROM demo_sales';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_unexpected_fields_are_rejected(): void
    {
        $this->validSpec['raw_sql'] = 'not allowed';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_fields_outside_the_approved_catalog_are_rejected(): void
    {
        $this->validSpec['visualizations'][0]['query']['metrics'][0]['field'] = 'invented_revenue';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_non_numeric_fields_cannot_be_summed(): void
    {
        $this->validSpec['visualizations'][0]['query']['metrics'][0]['field'] = 'region';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_order_by_must_reference_a_dimension_or_metric_alias(): void
    {
        $this->validSpec['visualizations'][3]['query']['order_by'] = [['field' => 'total_amount', 'direction' => 'desc']];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('order_by');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_order_by_accepts_dimensions_and_metric_aliases(): void
    {
        $this->validSpec['visualizations'][3]['query']['order_by'] = [
            ['field' => 'region', 'direction' => 'asc'],
            ['field' => 'total_sales', 'direction' => 'desc'],
        ];

        $spec = DashboardSpec::fromArray($this->validSpec);

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    public function test_version_1_1_supports_area_and_pie_visualizations(): void
    {
        $types = array_column($this->validSpec['visualizations'], 'type');

        $this->assertContains('pie', $types);
        $this->assertSame('1.1', $this->validSpec['version']);

        $this->validSpec['visualizations'][3]['type'] = 'area';
        $spec = DashboardSpec::fromArray($this->validSpec);

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    public function test_version_1_0_specs_remain_valid_but_cannot_use_new_types(): void
    {
        $legacy = $this->validSpec;
        $legacy['version'] = '1.0';
        $legacy['visualizations'] = [$legacy['visualizations'][0], $legacy['visualizations'][3]];
        $spec = DashboardSpec::fromArray($legacy);

        $this->assertSame($spec, $this->validator->validate($spec));

        $legacy['visualizations'][1]['type'] = 'area';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($legacy));
    }

    public function test_unknown_versions_are_rejected(): void
    {
        $this->validSpec['version'] = '2.0';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('$.version');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_pie_display_must_reference_a_dimension_and_a_metric_alias(): void
    {
        $pieIndex = array_search('pie', array_column($this->validSpec['visualizations'], 'type'), true);
        $this->validSpec['visualizations'][$pieIndex]['display']['category'] = 'total_sales';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('category');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_pie_display_rejects_axis_keys(): void
    {
        $pieIndex = array_search('pie', array_column($this->validSpec['visualizations'], 'type'), true);
        $this->validSpec['visualizations'][$pieIndex]['display'] = ['x_axis' => 'region', 'y_axis' => 'total_sales'];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_overlapping_layouts_are_rejected(): void
    {
        $this->validSpec['visualizations'][4]['layout'] = $this->validSpec['visualizations'][3]['layout'];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('overlaps');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_touching_layouts_are_accepted(): void
    {
        // The fake dashboard places a 7-wide chart next to a 5-wide pie on the same rows.
        $spec = DashboardSpec::fromArray($this->validSpec);

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    public function test_pie_is_limited_to_the_palette_size(): void
    {
        $pieIndex = array_search('pie', array_column($this->validSpec['visualizations'], 'type'), true);
        $this->validSpec['visualizations'][$pieIndex]['query']['limit'] = 7;

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('slices');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_pie_requires_exactly_one_dimension(): void
    {
        $pieIndex = array_search('pie', array_column($this->validSpec['visualizations'], 'type'), true);
        $this->validSpec['visualizations'][$pieIndex]['query']['dimensions'][] = ['field' => 'team'];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('exactly one dimension');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    public function test_metric_alias_cannot_shadow_a_dataset_field(): void
    {
        $this->validSpec['visualizations'][0]['query']['metrics'][0]['alias'] = 'region';

        $this->expectException(InvalidDashboardSpecException::class);
        $this->expectExceptionMessage('alias');
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $filter
     */
    #[DataProvider('coherentFilters')]
    public function test_coherent_filters_are_accepted(array $filter): void
    {
        $this->validSpec['global_filters'] = [$filter];
        $spec = DashboardSpec::fromArray($this->validSpec);

        $this->assertSame($spec, $this->validator->validate($spec));
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $filter
     */
    #[DataProvider('incoherentFilters')]
    public function test_incoherent_filters_are_rejected(array $filter): void
    {
        $this->validSpec['global_filters'] = [$filter];

        $this->expectException(InvalidDashboardSpecException::class);
        $this->validator->validate(DashboardSpec::fromArray($this->validSpec));
    }

    /**
     * @return array<string, array{array{field: string, operator: string, value: mixed}}>
     */
    public static function coherentFilters(): array
    {
        return [
            'equals string' => [['field' => 'region', 'operator' => 'equals', 'value' => 'Norte']],
            'not_equals numeric' => [['field' => 'quantity', 'operator' => 'not_equals', 'value' => 3]],
            'in strings' => [['field' => 'region', 'operator' => 'in', 'value' => ['Norte', 'Sur']]],
            'greater_than numeric' => [['field' => 'total_amount', 'operator' => 'greater_than', 'value' => 100.5]],
            'less_than_or_equal date' => [['field' => 'sold_at', 'operator' => 'less_than_or_equal', 'value' => '2026-01-31']],
            'between dates' => [['field' => 'sold_at', 'operator' => 'between', 'value' => ['2026-01-01', '2026-03-31']]],
            'between numbers' => [['field' => 'quantity', 'operator' => 'between', 'value' => [1, 10]]],
            'contains string' => [['field' => 'product_name', 'operator' => 'contains', 'value' => 'Pro']],
            'is_null' => [['field' => 'team', 'operator' => 'is_null', 'value' => null]],
            'previous_quarter' => [['field' => 'sold_at', 'operator' => 'previous_quarter', 'value' => null]],
            'previous_period' => [['field' => 'sold_at', 'operator' => 'previous_period', 'value' => null]],
        ];
    }

    /**
     * @return array<string, array{array{field: string, operator: string, value: mixed}}>
     */
    public static function incoherentFilters(): array
    {
        return [
            'equals with null' => [['field' => 'region', 'operator' => 'equals', 'value' => null]],
            'equals with list' => [['field' => 'region', 'operator' => 'equals', 'value' => ['Norte']]],
            'equals wrong type' => [['field' => 'quantity', 'operator' => 'equals', 'value' => 'tres']],
            'in with scalar' => [['field' => 'region', 'operator' => 'in', 'value' => 'Norte']],
            'in with empty list' => [['field' => 'region', 'operator' => 'in', 'value' => []]],
            'greater_than on string field' => [['field' => 'region', 'operator' => 'greater_than', 'value' => 'A']],
            'greater_than with list' => [['field' => 'total_amount', 'operator' => 'greater_than', 'value' => [1]]],
            'greater_than with bad date' => [['field' => 'sold_at', 'operator' => 'greater_than', 'value' => 'ayer']],
            'between with one value' => [['field' => 'quantity', 'operator' => 'between', 'value' => [1]]],
            'between with three values' => [['field' => 'quantity', 'operator' => 'between', 'value' => [1, 2, 3]]],
            'between with scalar' => [['field' => 'quantity', 'operator' => 'between', 'value' => 5]],
            'contains on numeric field' => [['field' => 'quantity', 'operator' => 'contains', 'value' => '1']],
            'contains with empty string' => [['field' => 'region', 'operator' => 'contains', 'value' => '']],
            'is_null with value' => [['field' => 'team', 'operator' => 'is_null', 'value' => 'x']],
            'previous_quarter on non-date' => [['field' => 'region', 'operator' => 'previous_quarter', 'value' => null]],
            'previous_period with value' => [['field' => 'sold_at', 'operator' => 'previous_period', 'value' => '2026-01-01']],
        ];
    }
}
