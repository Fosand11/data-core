import type { ChartTheme } from '@/composables/useChartTheme';
import type { DashboardVisualization, VisualizationResult } from '@/types';
import { describe, expect, it } from 'vitest';
import { SINGLE_COLOR_TYPES, blend, buildChartOption, describeChart, gaugeData, readableOn, scatterData, toRgb } from './chartOptions';

const theme: ChartTheme = {
    series: ['#0E8F63', '#E0A31B', '#3B7DD8', '#E5634B', '#8A6FD6', '#14A3B8'],
    surface: '#ffffff',
    text: '#14211C',
    muted: '#5A6962',
    grid: '#DDE3DE',
};

const base = (type: DashboardVisualization['type'], display: DashboardVisualization['display']): DashboardVisualization => ({
    key: 'chart',
    title: 'Gráfica',
    type,
    query: { metrics: [], dimensions: [], filters: [], order_by: [], limit: 10 },
    display,
    layout: { x: 0, y: 0, width: 6, height: 4 },
});

const byRegion: VisualizationResult = {
    columns: [
        { key: 'region', role: 'dimension', type: 'string', format: null },
        { key: 'total', role: 'metric', type: 'number', format: 'currency' },
        { key: 'units', role: 'metric', type: 'number', format: 'integer' },
    ],
    rows: [
        { region: 'Norte', total: 150, units: 3 },
        { region: 'Sur', total: 30, units: 3 },
        { region: 'Este', total: 80, units: 4 },
    ],
};

// ECharts options are plain objects; the helpers below read them without depending on its types.
type Loose = Record<string, any>;
const seriesOf = (option: unknown): Loose => (option as Loose).series[0];

describe('single-series charts take the colour the author picked', () => {
    const cases: Array<[DashboardVisualization['type'], DashboardVisualization['display']]> = [
        ['bar', { x_axis: 'region', y_axis: 'total' }],
        ['hbar', { x_axis: 'region', y_axis: 'total' }],
        ['line', { x_axis: 'region', y_axis: 'total' }],
        ['area', { x_axis: 'region', y_axis: 'total' }],
        ['radar', { category: 'region', value: 'total' }],
        ['scatter', { x_axis: 'total', y_axis: 'units', category: 'region' }],
        ['gauge', { value: 'total', max: 200 }],
    ];

    it.each(cases)('%s draws with the chosen slot, not always the first', (type, display) => {
        const first = JSON.stringify(buildChartOption(base(type, display), byRegion, theme, 0));
        const third = JSON.stringify(buildChartOption(base(type, display), byRegion, theme, 2));

        expect(first).toContain('#0E8F63');
        expect(third).toContain('#3B7DD8');
        expect(third).not.toContain('#0E8F63');
    });

    it('lists exactly those types as colour-pickable, and never the categorical ones', () => {
        expect(SINGLE_COLOR_TYPES).toEqual(expect.arrayContaining(['bar', 'hbar', 'line', 'area', 'radar', 'scatter', 'gauge', 'treemap']));
        expect(SINGLE_COLOR_TYPES).not.toContain('pie');
        expect(SINGLE_COLOR_TYPES).not.toContain('funnel');
    });

    it('ignores a slot that does not exist instead of drawing with an undefined colour', () => {
        const option = JSON.stringify(buildChartOption(base('bar', { x_axis: 'region', y_axis: 'total' }), byRegion, theme, 9));

        expect(option).toContain('#0E8F63');
        expect(option).not.toContain('undefined');
    });
});

describe('categorical charts keep the whole palette in order', () => {
    it('a funnel uses every palette colour whatever the accent', () => {
        const option = buildChartOption(base('funnel', { category: 'region', value: 'total' }), byRegion, theme, 3) as Loose;

        expect(option.color).toEqual(theme.series);
        // Largest stage first.
        expect(seriesOf(option).data.map((item: Loose) => item.name)).toEqual(['Norte', 'Este', 'Sur']);
    });

    it('a pie is unaffected by the accent', () => {
        const a = JSON.stringify(buildChartOption(base('pie', { category: 'region', value: 'total' }), byRegion, theme, 0));
        const b = JSON.stringify(buildChartOption(base('pie', { category: 'region', value: 'total' }), byRegion, theme, 4));

        expect(a).toBe(b);
    });
});

describe('treemap', () => {
    it('shades one hue by share, largest darkest, and picks readable label colours', () => {
        const series = seriesOf(buildChartOption(base('treemap', { category: 'region', value: 'total' }), byRegion, theme, 2));
        const [largest, , smallest] = series.data as Loose[];

        expect(largest.name).toBe('Norte');
        expect(smallest.name).toBe('Sur');
        expect(largest.itemStyle.color).not.toBe(smallest.itemStyle.color);
        expect(['#FFFFFF', '#14211C']).toContain(largest.label.color);
        expect(['#FFFFFF', '#14211C']).toContain(smallest.label.color);
    });
});

describe('radar', () => {
    it('gives every spoke the same scale, a little above the largest value', () => {
        const option = buildChartOption(base('radar', { category: 'region', value: 'total' }), byRegion, theme) as Loose;

        expect(option.radar.indicator.map((item: Loose) => item.name)).toEqual(['Norte', 'Sur', 'Este']);
        expect(new Set(option.radar.indicator.map((item: Loose) => item.max)).size).toBe(1);
        expect(option.radar.indicator[0].max).toBeCloseTo(150 * 1.15);
        expect(seriesOf(option).data[0].value).toEqual([150, 30, 80]);
    });

    it('does not produce a zero-sized axis when every value is zero', () => {
        const zeros = { ...byRegion, rows: byRegion.rows.map((row) => ({ ...row, total: 0 })) };
        const option = buildChartOption(base('radar', { category: 'region', value: 'total' }), zeros, theme) as Loose;

        expect(option.radar.indicator.every((item: Loose) => item.max > 0)).toBe(true);
    });
});

describe('scatter', () => {
    const scatter = base('scatter', { x_axis: 'total', y_axis: 'units', category: 'region' });

    it('plots one point per category with both measures', () => {
        const data = scatterData(scatter, byRegion);

        expect(data.points).toEqual([
            { label: 'Norte', x: 150, y: 3 },
            { label: 'Sur', x: 30, y: 3 },
            { label: 'Este', x: 80, y: 4 },
        ]);
        expect(seriesOf(buildChartOption(scatter, byRegion, theme)).data[0]).toEqual({ name: 'Norte', value: [150, 3] });
    });

    it('escapes names that come from the data before they reach the tooltip', () => {
        const hostile: VisualizationResult = { ...byRegion, rows: [{ region: '<img src=x onerror=alert(1)>', total: 1, units: 2 }] };
        const option = buildChartOption(scatter, hostile, theme) as Loose;
        const html = option.tooltip.formatter({ name: '<img src=x onerror=alert(1)>', value: [1, 2] });

        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;img');
    });

    it('describes itself for assistive technology', () => {
        const text = describeChart(scatter, byRegion);

        expect(text.startsWith('Gráfico de dispersión: Gráfica.')).toBe(true);
        expect(text).toContain('Norte');
    });
});

describe('gauge', () => {
    const gauge = base('gauge', { value: 'total', max: 200 });
    const one: VisualizationResult = { columns: [{ key: 'total', role: 'metric', type: 'number', format: 'number' }], rows: [{ total: 150 }] };

    it('reads the single value and its maximum', () => {
        expect(gaugeData(gauge, one)).toMatchObject({ value: 150, max: 200 });
        expect(seriesOf(buildChartOption(gauge, one, theme)).max).toBe(200);
    });

    it('survives an empty result and a missing or invalid maximum', () => {
        expect(gaugeData(gauge, { columns: [], rows: [] }).value).toBe(0);
        expect(gaugeData(base('gauge', { value: 'total', max: -3 }), one).max).toBe(1);
        expect(gaugeData(base('gauge', { value: 'total' }), one).max).toBe(1);
    });

    it('describes the value against its maximum', () => {
        expect(describeChart(gauge, one)).toMatch(/^Medidor: Gráfica\..* 150 de 200\.$/);
    });
});

describe('describeChart for the category and axis types', () => {
    it.each([
        ['hbar', 'Gráfico de barras horizontales', { x_axis: 'region', y_axis: 'total' }],
        ['funnel', 'Gráfico de embudo', { category: 'region', value: 'total' }],
        ['treemap', 'Mapa de árbol', { category: 'region', value: 'total' }],
        ['radar', 'Gráfico de radar', { category: 'region', value: 'total' }],
    ] as const)('%s', (type, name, display) => {
        expect(describeChart(base(type, display), byRegion).startsWith(`${name}: Gráfica.`)).toBe(true);
    });
});

describe('colour helpers', () => {
    it('reads hex and the hsl form the theme tokens use', () => {
        expect(toRgb('#0E8F63')).toEqual([14, 143, 99]);
        expect(toRgb('#fff')).toEqual([255, 255, 255]);
        expect(toRgb('hsl(0, 0%, 100%)')).toEqual([255, 255, 255]);
        expect(toRgb('hsl(147, 18%, 10%)')).toEqual([21, 30, 25]);
        expect(toRgb('papayawhip')).toBeNull();
    });

    it('blends a colour over the surface', () => {
        expect(blend('#000000', '#ffffff', 0.5)).toBe('rgb(128, 128, 128)');
        expect(blend('#0E8F63', '#ffffff', 1)).toBe('rgb(14, 143, 99)');
        expect(blend('nonsense', '#ffffff', 0.5)).toBe('nonsense');
    });

    it('chooses white text on dark fills and dark text on light ones', () => {
        expect(readableOn('#0B3D2B')).toBe('#FFFFFF');
        expect(readableOn('#E0A31B')).toBe('#14211C');
        expect(readableOn('rgb(255, 255, 255)')).toBe('#14211C');
        expect(readableOn('rgb(10, 20, 15)')).toBe('#FFFFFF');
    });
});
