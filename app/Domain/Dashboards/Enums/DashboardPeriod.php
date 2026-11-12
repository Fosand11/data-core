<?php

namespace App\Domain\Dashboards\Enums;

use Carbon\CarbonImmutable;

/**
 * The period a viewer applies on top of a dashboard. `Spec` keeps whatever date
 * filters the dashboard defines; every other case replaces them. Ranges are
 * half-open ([from, to)) so no instant is counted twice.
 */
enum DashboardPeriod: string
{
    case Spec = 'spec';
    case Last30Days = 'last_30_days';
    case ThisMonth = 'this_month';
    case PreviousMonth = 'previous_month';
    case ThisQuarter = 'this_quarter';
    case PreviousQuarter = 'previous_quarter';
    case ThisYear = 'this_year';
    case Last12Months = 'last_12_months';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Spec => 'Según el dashboard',
            self::Last30Days => 'Últimos 30 días',
            self::ThisMonth => 'Este mes',
            self::PreviousMonth => 'Mes anterior',
            self::ThisQuarter => 'Este trimestre',
            self::PreviousQuarter => 'Trimestre anterior',
            self::ThisYear => 'Este año',
            self::Last12Months => 'Últimos 12 meses',
            self::All => 'Todo el periodo',
        };
    }

    /**
     * Whether this period replaces the date filters defined by the dashboard.
     */
    public function overridesSpec(): bool
    {
        return $this !== self::Spec;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null null means "no date restriction"
     */
    public function range(CarbonImmutable $now): ?array
    {
        $today = $now->startOfDay();

        return match ($this) {
            self::Spec, self::All => null,
            self::Last30Days => [$today->subDays(29), $today->addDay()],
            self::ThisMonth => [$now->startOfMonth(), $now->startOfMonth()->addMonthNoOverflow()],
            self::PreviousMonth => [$now->startOfMonth()->subMonthNoOverflow(), $now->startOfMonth()],
            self::ThisQuarter => [$now->startOfQuarter(), $now->startOfQuarter()->addQuarter()],
            self::PreviousQuarter => [$now->startOfQuarter()->subQuarter(), $now->startOfQuarter()],
            self::ThisYear => [$now->startOfYear(), $now->startOfYear()->addYear()],
            self::Last12Months => [$now->startOfMonth()->subMonthsNoOverflow(11), $now->startOfMonth()->addMonthNoOverflow()],
        };
    }
}
