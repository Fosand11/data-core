import type { DashboardVisualization, VisualizationResult } from '@/types';
import { describe, expect, it } from 'vitest';
import { MAX_PIE_SLICES, chartData, describeChart, hasPartialPeriod } from './chartOptions';

const line: DashboardVisualization = {
    key: 'trend',
    title: 'Evolución',
    type: 'line',
    query: { metrics: [], dimensions: [], filters: [], order_by: [], limit: 24 },
    display: { x_axis: 'sold_at', y_axis: 'total' },
    layout: { x: 0, y: 0, width: 12, height: 4 },
};

const monthly = (rows: string[], currentMonth: string | undefined): VisualizationResult => ({
    columns: [
        { key: 'sold_at', role: 'dimension', type: 'month', format: null },
        { key: 'total', role: 'metric', type: 'number', format: 'currency' },
    ],
    rows: rows.map((month, index) => ({ sold_at: month, total: (index + 1) * 100 })),
    meta: currentMonth === undefined ? undefined : { current_month: currentMonth },
});

describe('chartData', () => {
    it('flags only the month in progress as partial', () => {
        const { points } = chartData(line, monthly(['2026-09', '2026-10', '2026-11'], '2026-11'));

        expect(points.map((point) => point.partial)).toEqual([false, false, true]);
        expect(points[2].label.endsWith(' (en curso)')).toBe(true);
    });

    it('never flags anything for a closed period', () => {
        const result = monthly(['2026-07', '2026-08', '2026-09'], '2026-11');

        expect(hasPartialPeriod(line, result)).toBe(false);
    });

    it('caps pie slices to the size of the validated palette', () => {
        const pie: DashboardVisualization = { ...line, type: 'pie', display: { category: 'region', value: 'total' } };
        const result: VisualizationResult = {
            columns: [
                { key: 'region', role: 'dimension', type: 'string', format: null },
                { key: 'total', role: 'metric', type: 'number', format: 'number' },
            ],
            rows: Array.from({ length: 9 }, (_, index) => ({ region: `R${index}`, total: index })),
        };

        expect(chartData(pie, result).points).toHaveLength(MAX_PIE_SLICES);
        expect(hasPartialPeriod(pie, result)).toBe(false);
    });
});

describe('describeChart', () => {
    it('summarises the first points and always mentions the month in progress', () => {
        const months = ['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-11'];
        const text = describeChart(line, monthly(months, '2026-11'));

        expect(text.startsWith('Gráfico de línea: Evolución.')).toBe(true);
        expect(text).toContain('y 3 más');
        expect(text).toContain('es el mes en curso y aún está incompleto');
    });

    it('says so when there is no data', () => {
        expect(describeChart(line, { columns: [], rows: [] })).toBe('Gráfico de línea: Evolución. Sin datos.');
    });
});
