<?php

namespace Tests\Unit\Domain\Dashboards;

use App\Domain\Dashboards\Enums\DashboardPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DashboardPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{DashboardPeriod, string, string, string}>
     */
    public static function ranges(): iterable
    {
        // [period, now, expected from (inclusive), expected to (exclusive)]
        yield 'last 30 days includes today' => [DashboardPeriod::Last30Days, '2026-11-12 15:30:00', '2026-10-14 00:00:00', '2026-11-13 00:00:00'];
        yield 'this month' => [DashboardPeriod::ThisMonth, '2026-11-12 15:30:00', '2026-11-01 00:00:00', '2026-12-01 00:00:00'];
        yield 'previous month' => [DashboardPeriod::PreviousMonth, '2026-11-12 15:30:00', '2026-10-01 00:00:00', '2026-11-01 00:00:00'];
        yield 'previous month on the 31st does not overflow' => [DashboardPeriod::PreviousMonth, '2026-03-31 10:00:00', '2026-02-01 00:00:00', '2026-03-01 00:00:00'];
        yield 'this quarter' => [DashboardPeriod::ThisQuarter, '2026-11-12 15:30:00', '2026-10-01 00:00:00', '2027-01-01 00:00:00'];
        yield 'previous quarter' => [DashboardPeriod::PreviousQuarter, '2026-11-12 15:30:00', '2026-07-01 00:00:00', '2026-10-01 00:00:00'];
        yield 'previous quarter in January crosses the year' => [DashboardPeriod::PreviousQuarter, '2027-01-15 09:00:00', '2026-10-01 00:00:00', '2027-01-01 00:00:00'];
        yield 'this year' => [DashboardPeriod::ThisYear, '2026-11-12 15:30:00', '2026-01-01 00:00:00', '2027-01-01 00:00:00'];
        yield 'last 12 months covers twelve calendar months' => [DashboardPeriod::Last12Months, '2026-11-12 15:30:00', '2025-12-01 00:00:00', '2026-12-01 00:00:00'];
        yield 'last 12 months on the 31st' => [DashboardPeriod::Last12Months, '2026-03-31 10:00:00', '2025-04-01 00:00:00', '2026-04-01 00:00:00'];
    }

    #[DataProvider('ranges')]
    public function test_each_period_resolves_to_a_half_open_range(DashboardPeriod $period, string $now, string $from, string $to): void
    {
        $range = $period->range(CarbonImmutable::parse($now));

        $this->assertNotNull($range);
        $this->assertSame($from, $range[0]->format('Y-m-d H:i:s'));
        $this->assertSame($to, $range[1]->format('Y-m-d H:i:s'));
    }

    public function test_the_spec_and_all_periods_have_no_range_but_only_all_overrides_the_spec(): void
    {
        $now = CarbonImmutable::parse('2026-11-12');

        $this->assertNull(DashboardPeriod::Spec->range($now));
        $this->assertNull(DashboardPeriod::All->range($now));
        $this->assertFalse(DashboardPeriod::Spec->overridesSpec());
        $this->assertTrue(DashboardPeriod::All->overridesSpec());
        $this->assertTrue(DashboardPeriod::ThisMonth->overridesSpec());
    }

    public function test_every_period_has_a_label_and_a_unique_value(): void
    {
        $values = array_map(fn (DashboardPeriod $period): string => $period->value, DashboardPeriod::cases());

        $this->assertSame($values, array_values(array_unique($values)));

        foreach (DashboardPeriod::cases() as $period) {
            $this->assertNotSame('', $period->label());
        }
    }
}
