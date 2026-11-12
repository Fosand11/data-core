<?php

namespace Tests\Unit\Domain\Dashboards;

use App\Domain\Dashboards\Support\ExecutionTime;
use PHPUnit\Framework\TestCase;

class ExecutionTimeTest extends TestCase
{
    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = (string) ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        ini_set('max_execution_time', $this->original);

        parent::tearDown();
    }

    public function test_a_web_request_limit_shorter_than_the_job_is_extended(): void
    {
        ini_set('max_execution_time', '30');

        ExecutionTime::allow(230);

        $this->assertSame(230, (int) ini_get('max_execution_time'));
    }

    public function test_a_limit_that_is_already_long_enough_is_kept(): void
    {
        ini_set('max_execution_time', '600');

        ExecutionTime::allow(230);

        $this->assertSame(600, (int) ini_get('max_execution_time'));
    }

    public function test_unlimited_execution_is_left_alone(): void
    {
        ini_set('max_execution_time', '0');

        ExecutionTime::allow(230);

        $this->assertSame(0, (int) ini_get('max_execution_time'));
    }
}
