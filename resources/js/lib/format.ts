import type { VisualizationColumn } from '@/types';

const LOCALE = 'es-US';

type MetricFormat = VisualizationColumn['format'] | undefined;

const decimals = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 });
const integers = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 0 });
const currency = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
const currencyWhole = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat(LOCALE, { notation: 'compact', maximumFractionDigits: 1 });
const compactCurrency = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'USD', notation: 'compact', maximumFractionDigits: 1 });
const monthFormatter = new Intl.DateTimeFormat(LOCALE, { month: 'short', year: 'numeric', timeZone: 'UTC' });

export const MISSING_LABEL = 'Sin dato';
export const IN_PROGRESS_SUFFIX = ' (en curso)';

/**
 * Formats a metric value. `compact` is meant for axes, labels and KPI tiles,
 * where 12,900 reads better as 12.9K; tables and tooltips keep full precision.
 */
export function formatValue(value: unknown, format: MetricFormat, options: { compact?: boolean } = {}): string {
    if (value === null || value === undefined || value === '') return '—';

    const number = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(number)) return String(value);

    // Compact notation only pays off from the thousands; below that keep full precision.
    if (options.compact && Math.abs(number) >= 1000) {
        return tightenCompact(compactFor(format).format(number));
    }

    switch (format) {
        case 'currency':
            // Whole amounts ("$0", "$1,200") skip the cents; fractional ones keep them ("$824.38").
            return Math.abs(number) >= 1000 || (options.compact && Number.isInteger(number)) ? currencyWhole.format(number) : currency.format(number);
        case 'integer':
            return integers.format(number);
        case 'percent':
            return `${decimals.format(number)}%`;
        default:
            return decimals.format(number);
    }
}

const compactFor = (format: MetricFormat) => (format === 'currency' ? compactCurrency : compact);

/** "$175.6 K" -> "$175.6K": drop the space Intl puts before the compact suffix. */
const tightenCompact = (text: string): string => text.replace(/(\d)[\s\u00a0\u202f]+([A-Za-z]+)$/, '$1$2');

/** Whether a raw dimension value is the month still in progress (its bucket is incomplete). */
export function isCurrentMonth(value: unknown, column: Pick<VisualizationColumn, 'type'> | undefined, currentMonth: string | undefined): boolean {
    return column?.type === 'month' && currentMonth !== undefined && value === currentMonth;
}

/** Formats a dimension value; months arrive as "YYYY-MM" and the month in progress is flagged. */
export function formatDimension(value: unknown, column: Pick<VisualizationColumn, 'type'> | undefined, currentMonth?: string): string {
    if (value === null || value === undefined || value === '') return MISSING_LABEL;

    if (column?.type === 'month' && typeof value === 'string' && /^\d{4}-\d{2}$/.test(value)) {
        const label = monthFormatter.format(new Date(`${value}-01T00:00:00Z`));

        return isCurrentMonth(value, column, currentMonth) ? `${label}${IN_PROGRESS_SUFFIX}` : label;
    }

    return String(value);
}

export function formatCell(value: unknown, column: VisualizationColumn, currentMonth?: string): string {
    return column.role === 'metric' ? formatValue(value, column.format) : formatDimension(value, column, currentMonth);
}

const dayMonth = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short', timeZone: 'UTC' });
const dayMonthYear = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

/** "1 jul – 30 sept 2026"; the year is repeated on both ends only when it differs. */
export function formatRange(from: string, to: string): string {
    const start = new Date(`${from}T00:00:00Z`);
    const end = new Date(`${to}T00:00:00Z`);

    return start.getUTCFullYear() === end.getUTCFullYear()
        ? `${dayMonth.format(start)} – ${dayMonthYear.format(end)}`
        : `${dayMonthYear.format(start)} – ${dayMonthYear.format(end)}`;
}

const relativeFormatter = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

/** "hace 3 días", "ayer", "hace 5 minutos"; falls back to a short date after a month. */
export function formatRelative(iso: string, now: Date = new Date()): string {
    const date = new Date(iso);
    const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
    ];

    if (Math.abs(seconds) < 60) return 'justo ahora';

    for (let index = units.length - 1; index >= 0; index--) {
        const [unit, size] = units[index];

        if (Math.abs(seconds) >= size) {
            const value = Math.round(seconds / size);

            if (unit === 'day' && Math.abs(value) > 30) break;

            return relativeFormatter.format(value, unit);
        }
    }

    return new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}
