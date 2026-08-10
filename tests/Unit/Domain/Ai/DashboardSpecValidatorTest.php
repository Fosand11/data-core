<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
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
}
