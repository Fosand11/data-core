import type { ChartTheme } from '@/composables/useChartTheme';
import { IN_PROGRESS_SUFFIX, formatDimension, formatValue, isCurrentMonth } from '@/lib/format';
import { labelFor } from '@/lib/labels';
import type { DashboardVisualization, VisualizationColumn, VisualizationResult } from '@/types';
import type { EChartsOption } from 'echarts';

/** The validated categorical palette has six slots; pie slices never go beyond it. */
export const MAX_PIE_SLICES = 6;

/** Charts that show one measure across the categories of one dimension. */
const CATEGORY_TYPES: DashboardVisualization['type'][] = ['pie', 'funnel', 'treemap', 'radar'];

/** Charts whose x-axis is a dimension (so a month in progress can be one of its points). */
const AXIS_TYPES: DashboardVisualization['type'][] = ['bar', 'hbar', 'line', 'area'];

/** Charts that draw a single series and therefore take the colour the author picked for them. */
export const SINGLE_COLOR_TYPES: DashboardVisualization['type'][] = ['bar', 'hbar', 'line', 'area', 'treemap', 'radar', 'scatter', 'gauge'];

const isCategoryChart = (visualization: DashboardVisualization) => CATEGORY_TYPES.includes(visualization.type);

const MAX_LABELLED_BARS = 8;

export interface ChartPoint {
    label: string;
    value: number;
    /** True for the month still in progress: its value is incomplete and drawn as such. */
    partial: boolean;
}

export interface ChartSeriesData {
    metric: VisualizationColumn | undefined;
    metricLabel: string;
    points: ChartPoint[];
}

/** Resolves the category/value pair a chart plots from its display config and the query result. */
export function chartData(visualization: DashboardVisualization, result: VisualizationResult): ChartSeriesData {
    const display = visualization.display;
    const categoryKey = String(isCategoryChart(visualization) ? display.category : display.x_axis);
    const valueKey = String(isCategoryChart(visualization) ? display.value : display.y_axis);
    const dimension = result.columns.find((column) => column.key === categoryKey);
    const metric = result.columns.find((column) => column.key === valueKey);
    const rows = visualization.type === 'pie' ? result.rows.slice(0, MAX_PIE_SLICES) : result.rows;

    return {
        metric,
        metricLabel: labelFor(valueKey),
        points: rows.map((row) => ({
            label: formatDimension(row[categoryKey], dimension, result.meta?.current_month),
            value: Number(row[valueKey] ?? 0),
            partial: isCurrentMonth(row[categoryKey], dimension, result.meta?.current_month),
        })),
    };
}

/** Whether the chart plots a month that is still in progress (and therefore incomplete). */
export function hasPartialPeriod(visualization: DashboardVisualization, result: VisualizationResult): boolean {
    return AXIS_TYPES.includes(visualization.type) && chartData(visualization, result).points.some((point) => point.partial);
}

/** The axis shows a compact marker; the full "(en curso)" text stays in tooltips and tables. */
const axisLabel = (label: string) => (label.endsWith(IN_PROGRESS_SUFFIX) ? `${label.slice(0, -IN_PROGRESS_SUFFIX.length)}*` : label);

/** One-sentence text alternative of a chart, for assistive technology. */
export function describeChart(visualization: DashboardVisualization, result: VisualizationResult): string {
    if (visualization.type === 'scatter') return describeScatter(visualization, result);
    if (visualization.type === 'gauge') return describeGauge(visualization, result);

    const { metric, metricLabel, points } = chartData(visualization, result);
    const kinds: Partial<Record<DashboardVisualization['type'], string>> = {
        bar: 'Gráfico de barras',
        hbar: 'Gráfico de barras horizontales',
        line: 'Gráfico de línea',
        area: 'Gráfico de área',
        pie: 'Gráfico circular',
        funnel: 'Gráfico de embudo',
        treemap: 'Mapa de árbol',
        radar: 'Gráfico de radar',
    };
    const kind = kinds[visualization.type] ?? 'Gráfico';

    if (points.length === 0) return `${kind}: ${visualization.title}. Sin datos.`;

    const sample = points
        .slice(0, 6)
        .map((point) => `${point.label}: ${formatValue(point.value, metric?.format)}`)
        .join('; ');
    const rest = points.length > 6 ? `; y ${points.length - 6} más` : '';

    // The sample may cut off the most recent points, so the in-progress month is always stated.
    const partial = points.find((point) => point.partial);
    const note = partial ? ` ${partial.label.replace(IN_PROGRESS_SUFFIX, '')} es el mes en curso y aún está incompleto.` : '';

    return `${kind}: ${visualization.title}. ${metricLabel} — ${sample}${rest}.${note}`;
}

/**
 * `accent` is the palette slot the author chose for a single-series chart; categorical
 * charts (pie, funnel) always use the whole palette in its fixed order.
 */
export function buildChartOption(visualization: DashboardVisualization, result: VisualizationResult, theme: ChartTheme, accent = 0): EChartsOption {
    const color = theme.series[accent] ?? theme.series[0];

    if (visualization.type === 'scatter') return scatterOption(scatterData(visualization, result), theme, color);
    if (visualization.type === 'gauge') return gaugeOption(gaugeData(visualization, result), theme, color);

    const data = chartData(visualization, result);

    switch (visualization.type) {
        case 'bar':
            return barOption(data, theme, color);
        case 'hbar':
            return barOption(data, theme, color, true);
        case 'line':
        case 'area':
            return trendOption(data, theme, visualization.type === 'area', color);
        case 'pie':
            return pieOption(data, theme);
        case 'funnel':
            return funnelOption(data, theme);
        case 'treemap':
            return treemapOption(data, theme, color);
        case 'radar':
            return radarOption(data, theme, color);
        default:
            return {};
    }
}

const tooltipBase = (theme: ChartTheme, metric: VisualizationColumn | undefined) => ({
    backgroundColor: theme.surface,
    borderColor: theme.grid,
    borderWidth: 1,
    textStyle: { color: theme.text, fontSize: 12 },
    // The default tooltip renderer escapes names, so no HTML is built from data here.
    valueFormatter: (value: unknown) => formatValue(value, metric?.format),
});

const axisText = (theme: ChartTheme) => ({ color: theme.muted, fontSize: 12 });

const hairline = (theme: ChartTheme) => ({ color: theme.grid, width: 1, type: 'solid' as const });

function barOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme, color: string, forceHorizontal = false): EChartsOption {
    // Long names or many categories read better as horizontal bars.
    const horizontal = forceHorizontal || points.length > 5 || points.some((point) => point.label.length > 12);
    const labelled = points.length <= MAX_LABELLED_BARS;
    const categories = points.map((point) => point.label);

    const categoryAxis = {
        type: 'category' as const,
        data: categories,
        inverse: horizontal,
        axisTick: { show: false },
        axisLine: { lineStyle: hairline(theme) },
        axisLabel: {
            ...axisText(theme),
            hideOverlap: true,
            width: horizontal ? 110 : undefined,
            overflow: 'truncate' as const,
            formatter: axisLabel,
        },
    };
    const valueAxis = {
        type: 'value' as const,
        axisLine: { show: false },
        axisTick: { show: false },
        splitLine: { lineStyle: hairline(theme) },
        splitNumber: 4,
        axisLabel: { ...axisText(theme), hideOverlap: true, formatter: (value: number) => formatValue(value, metric?.format, { compact: true }) },
    };

    return {
        animationDuration: 400,
        grid: { left: 8, right: labelled && horizontal ? 56 : 12, top: labelled && !horizontal ? 28 : 12, bottom: 8, containLabel: true },
        xAxis: horizontal ? valueAxis : categoryAxis,
        yAxis: horizontal ? categoryAxis : valueAxis,
        tooltip: { ...tooltipBase(theme, metric), trigger: 'item' },
        series: [
            {
                type: 'bar',
                name: metricLabel,
                data: points.map((point) => (point.partial ? { value: point.value, itemStyle: { opacity: 0.45 } } : point.value)),
                barMaxWidth: 24,
                itemStyle: { color, borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0] },
                emphasis: { itemStyle: { opacity: 0.85 } },
                label: {
                    show: labelled,
                    position: horizontal ? 'right' : 'top',
                    color: theme.text,
                    fontSize: 12,
                    formatter: ({ value }: { value: unknown }) => formatValue(value, metric?.format, { compact: true }),
                },
            },
        ],
    };
}

function trendOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme, filled: boolean, color: string): EChartsOption {
    return {
        animationDuration: 400,
        grid: { left: 8, right: 64, top: 16, bottom: 8, containLabel: true },
        tooltip: {
            ...tooltipBase(theme, metric),
            trigger: 'axis',
            axisPointer: { type: 'line', lineStyle: hairline(theme) },
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: points.map((point) => point.label),
            axisTick: { show: false },
            axisLine: { lineStyle: hairline(theme) },
            axisLabel: { ...axisText(theme), hideOverlap: true, formatter: axisLabel },
        },
        yAxis: {
            type: 'value',
            axisLine: { show: false },
            axisTick: { show: false },
            splitLine: { lineStyle: hairline(theme) },
            axisLabel: { ...axisText(theme), formatter: (value: number) => formatValue(value, metric?.format, { compact: true }) },
        },
        series: [
            {
                type: 'line',
                name: metricLabel,
                // Markers are drawn on every point of short series; on long ones only the in-progress month keeps one.
                // A hollow marker says "this value is still changing".
                data: points.map((point) => ({
                    value: point.value,
                    symbol: point.partial || points.length <= 14 ? 'circle' : 'none',
                    ...(point.partial ? { itemStyle: { color: theme.surface, borderColor: color, borderWidth: 2.5 }, symbolSize: 10 } : {}),
                })),
                smooth: false,
                symbol: 'circle',
                symbolSize: 8,
                showSymbol: true,
                lineStyle: { color, width: 2, cap: 'round', join: 'round' },
                // 2px ring in the surface color keeps markers legible over the line.
                itemStyle: { color, borderColor: theme.surface, borderWidth: 2 },
                areaStyle: filled ? { color, opacity: 0.1 } : undefined,
                // Label only the endpoint; the axis and tooltip carry the rest.
                endLabel: {
                    show: points.length > 0,
                    color: theme.text,
                    fontSize: 12,
                    formatter: ({ value }: { value: unknown }) => formatValue(value, metric?.format, { compact: true }),
                },
                emphasis: { focus: 'series' },
            },
        ],
    };
}

function pieOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme): EChartsOption {
    const valueByName = new Map(points.map((point) => [point.label, point.value]));

    const total = points.reduce((sum, point) => sum + point.value, 0);

    return {
        animationDuration: 400,
        color: theme.series,
        // The total sits in the donut hole so the ring only has to show the shares.
        title: {
            text: formatValue(total, metric?.format, { compact: true }),
            subtext: 'Total',
            left: 'center',
            top: '32%',
            itemGap: 4,
            textStyle: { color: theme.text, fontSize: 22, fontWeight: 600 },
            subtextStyle: { color: theme.muted, fontSize: 12 },
        },
        tooltip: { ...tooltipBase(theme, metric), trigger: 'item' },
        // Identity never relies on color alone: the legend names every slice and carries its value.
        legend: {
            bottom: 0,
            type: 'plain',
            icon: 'circle',
            itemWidth: 10,
            itemHeight: 10,
            itemGap: 14,
            textStyle: { color: theme.text, fontSize: 12 },
            formatter: (name: string) => `${name}  ${formatValue(valueByName.get(name), metric?.format, { compact: true })}`,
        },
        series: [
            {
                type: 'pie',
                name: metricLabel,
                radius: ['52%', '74%'],
                center: ['50%', '40%'],
                minAngle: 3,
                data: points.map((point) => ({ name: point.label, value: point.value })),
                // The surface-colored gap separates touching slices; no strokes around marks.
                itemStyle: { borderColor: theme.surface, borderWidth: 2, borderRadius: 4 },
                label: { show: false },
                labelLine: { show: false },
                emphasis: { scaleSize: 4 },
            },
        ],
    };
}

// ---------------------------------------------------------------------------------------------
// Colour helpers
// ---------------------------------------------------------------------------------------------

type Rgb = [number, number, number];

/** `#rrggbb`, `#rgb` or `hsl(h, s%, l%)` (the form the theme tokens use) -> 0..255 channels, or null when unknown. */
export function toRgb(color: string): Rgb | null {
    const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(color.trim());

    if (hex) {
        const digits = hex[1].length === 3 ? [...hex[1]].map((digit) => digit + digit).join('') : hex[1];

        return [0, 2, 4].map((offset) => parseInt(digits.slice(offset, offset + 2), 16)) as Rgb;
    }

    const hsl = /^hsla?\(\s*([\d.]+)[,\s]+([\d.]+)%[,\s]+([\d.]+)%/i.exec(color.trim());

    if (hsl) {
        const [h, s, l] = [Number(hsl[1]), Number(hsl[2]) / 100, Number(hsl[3]) / 100];
        const a = s * Math.min(l, 1 - l);
        const channel = (n: number) => {
            const k = (n + h / 30) % 12;

            return Math.round(255 * (l - a * Math.max(-1, Math.min(k - 3, Math.min(9 - k, 1)))));
        };

        return [channel(0), channel(8), channel(4)];
    }

    return null;
}

const luminance = ([r, g, b]: Rgb) => {
    const linear = (value: number) => {
        const unit = value / 255;

        return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * linear(r) + 0.7152 * linear(g) + 0.0722 * linear(b);
};

/** `color` painted at `alpha` over `surface`, as an opaque colour (so labels can be matched to it). */
export function blend(color: string, surface: string, alpha: number): string {
    const top = toRgb(color);
    const bottom = toRgb(surface);

    if (!top || !bottom) return color;

    const mixed = top.map((channel, index) => Math.round(channel * alpha + bottom[index] * (1 - alpha)));

    return `rgb(${mixed.join(', ')})`;
}

/** Dark or white text, whichever reads better on `fill`. */
export function readableOn(fill: string): string {
    const rgb = toRgb(
        fill.startsWith('rgb(')
            ? `#${fill
                  .slice(4, -1)
                  .split(',')
                  .map((part) => Number(part).toString(16).padStart(2, '0'))
                  .join('')}`
            : fill,
    );

    return rgb && luminance(rgb) < 0.4 ? '#FFFFFF' : '#14211C';
}

const escapeHtml = (text: string) =>
    text.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char);

// ---------------------------------------------------------------------------------------------
// Funnel, treemap and radar: one measure across the categories of one dimension
// ---------------------------------------------------------------------------------------------

function funnelOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme): EChartsOption {
    const sorted = [...points].sort((a, b) => b.value - a.value);

    return {
        animationDuration: 400,
        color: theme.series,
        tooltip: { ...tooltipBase(theme, metric), trigger: 'item' },
        series: [
            {
                type: 'funnel',
                name: metricLabel,
                left: '4%',
                width: '52%',
                top: 8,
                bottom: 8,
                sort: 'descending',
                gap: 3,
                minSize: '14%',
                data: sorted.map((point) => ({ name: point.label, value: point.value })),
                // The surface-coloured gap separates stages; every stage is named at its right, so colour never carries identity alone.
                itemStyle: { borderColor: theme.surface, borderWidth: 2, borderRadius: 4 },
                label: {
                    show: true,
                    position: 'right',
                    color: theme.text,
                    fontSize: 12,
                    formatter: ({ name, value }: { name: string; value: unknown }) =>
                        `${name}  ${formatValue(value, metric?.format, { compact: true })}`,
                },
                labelLine: { show: true, length: 14, lineStyle: { color: theme.grid, width: 1 } },
                emphasis: { itemStyle: { opacity: 0.85 } },
            },
        ],
    };
}

function treemapOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme, color: string): EChartsOption {
    const largest = Math.max(...points.map((point) => point.value), 0) || 1;

    return {
        animationDuration: 400,
        tooltip: { ...tooltipBase(theme, metric), trigger: 'item' },
        series: [
            {
                type: 'treemap',
                name: metricLabel,
                roam: false,
                nodeClick: false,
                breadcrumb: { show: false },
                left: 0,
                right: 0,
                top: 0,
                bottom: 0,
                itemStyle: { borderColor: theme.surface, borderWidth: 2, gapWidth: 2, borderRadius: 4 },
                label: { show: true, fontSize: 12, overflow: 'truncate' },
                // One hue, deeper for bigger shares: area already says "how much", the shade repeats it.
                data: [...points]
                    .sort((a, b) => b.value - a.value)
                    .map((point) => {
                        const fill = blend(color, theme.surface, 0.3 + 0.7 * (point.value / largest));

                        return {
                            name: point.label,
                            value: point.value,
                            itemStyle: { color: fill },
                            label: {
                                color: readableOn(fill),
                                formatter: `{b}\n${formatValue(point.value, metric?.format, { compact: true })}`,
                            },
                        };
                    }),
            },
        ],
    };
}

function radarOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme, color: string): EChartsOption {
    const largest = Math.max(...points.map((point) => point.value), 0);

    return {
        animationDuration: 400,
        tooltip: { ...tooltipBase(theme, metric), trigger: 'item' },
        radar: {
            indicator: points.map((point) => ({ name: point.label, max: largest > 0 ? largest * 1.15 : 1 })),
            radius: '62%',
            center: ['50%', '54%'],
            splitNumber: 4,
            axisName: { color: theme.text, fontSize: 12 },
            axisLine: { lineStyle: hairline(theme) },
            splitLine: { lineStyle: hairline(theme) },
            splitArea: { show: false },
        },
        series: [
            {
                type: 'radar',
                name: metricLabel,
                data: [{ name: metricLabel, value: points.map((point) => point.value) }],
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: { color, width: 2 },
                itemStyle: { color, borderColor: theme.surface, borderWidth: 2 },
                areaStyle: { color, opacity: 0.16 },
            },
        ],
    };
}

// ---------------------------------------------------------------------------------------------
// Scatter: two measures, one point per category
// ---------------------------------------------------------------------------------------------

export interface ScatterPoint {
    label: string;
    x: number;
    y: number;
}

export interface ScatterData {
    xMetric: VisualizationColumn | undefined;
    yMetric: VisualizationColumn | undefined;
    xLabel: string;
    yLabel: string;
    points: ScatterPoint[];
}

export function scatterData(visualization: DashboardVisualization, result: VisualizationResult): ScatterData {
    const { x_axis: xKey, y_axis: yKey, category: categoryKey } = visualization.display as Record<string, string>;
    const dimension = result.columns.find((column) => column.key === categoryKey);

    return {
        xMetric: result.columns.find((column) => column.key === xKey),
        yMetric: result.columns.find((column) => column.key === yKey),
        xLabel: labelFor(String(xKey)),
        yLabel: labelFor(String(yKey)),
        points: result.rows.map((row) => ({
            label: formatDimension(row[categoryKey], dimension, result.meta?.current_month),
            x: Number(row[xKey] ?? 0),
            y: Number(row[yKey] ?? 0),
        })),
    };
}

function describeScatter(visualization: DashboardVisualization, result: VisualizationResult): string {
    const { xMetric, yMetric, xLabel, yLabel, points } = scatterData(visualization, result);

    if (points.length === 0) return `Gráfico de dispersión: ${visualization.title}. Sin datos.`;

    const sample = points
        .slice(0, 5)
        .map((point) => `${point.label}: ${formatValue(point.x, xMetric?.format)} y ${formatValue(point.y, yMetric?.format)}`)
        .join('; ');

    return `Gráfico de dispersión: ${visualization.title}. ${xLabel} contra ${yLabel} — ${sample}${points.length > 5 ? `; y ${points.length - 5} más` : ''}.`;
}

function scatterOption({ xMetric, yMetric, xLabel, yLabel, points }: ScatterData, theme: ChartTheme, color: string): EChartsOption {
    const axis = (name: string, metric: VisualizationColumn | undefined) => ({
        type: 'value' as const,
        name,
        nameLocation: 'middle' as const,
        nameGap: 30,
        nameTextStyle: { color: theme.muted, fontSize: 12 },
        scale: true,
        axisLine: { show: false },
        axisTick: { show: false },
        splitLine: { lineStyle: hairline(theme) },
        axisLabel: { ...axisText(theme), hideOverlap: true, formatter: (value: number) => formatValue(value, metric?.format, { compact: true }) },
    });

    return {
        animationDuration: 400,
        grid: { left: 8, right: 24, top: 16, bottom: 28, containLabel: true },
        xAxis: axis(xLabel, xMetric),
        yAxis: { ...axis(yLabel, yMetric), nameGap: 44, nameRotate: 90 },
        tooltip: {
            backgroundColor: theme.surface,
            borderColor: theme.grid,
            borderWidth: 1,
            textStyle: { color: theme.text, fontSize: 12 },
            trigger: 'item',
            // Names come from the customer's data, so they are escaped before they become HTML.
            formatter: (params: unknown) => {
                const { name, value } = params as { name: string; value: [number, number] };

                return `${escapeHtml(name)}<br/>${escapeHtml(xLabel)}: ${escapeHtml(formatValue(value[0], xMetric?.format))}<br/>${escapeHtml(yLabel)}: ${escapeHtml(formatValue(value[1], yMetric?.format))}`;
            },
        },
        series: [
            {
                type: 'scatter',
                name: `${xLabel} / ${yLabel}`,
                symbolSize: 13,
                data: points.map((point) => ({ name: point.label, value: [point.x, point.y] })),
                itemStyle: { color, opacity: 0.9, borderColor: theme.surface, borderWidth: 2 },
                // Few points can be named outright; many are read from the tooltip.
                label: {
                    show: points.length <= 10,
                    position: 'top',
                    color: theme.text,
                    fontSize: 11,
                    formatter: ({ name }: { name: string }) => name,
                },
                emphasis: { scale: 1.25, itemStyle: { opacity: 1 } },
            },
        ],
    };
}

// ---------------------------------------------------------------------------------------------
// Gauge: one value against a maximum
// ---------------------------------------------------------------------------------------------

export interface GaugeData {
    metric: VisualizationColumn | undefined;
    metricLabel: string;
    value: number;
    max: number;
}

export function gaugeData(visualization: DashboardVisualization, result: VisualizationResult): GaugeData {
    const valueKey = String(visualization.display.value);
    const max = Number(visualization.display.max);

    return {
        metric: result.columns.find((column) => column.key === valueKey),
        metricLabel: labelFor(valueKey),
        value: Number(result.rows[0]?.[valueKey] ?? 0),
        max: Number.isFinite(max) && max > 0 ? max : 1,
    };
}

function describeGauge(visualization: DashboardVisualization, result: VisualizationResult): string {
    const { metric, metricLabel, value, max } = gaugeData(visualization, result);

    return `Medidor: ${visualization.title}. ${metricLabel}: ${formatValue(value, metric?.format)} de ${formatValue(max, metric?.format)}.`;
}

function gaugeOption({ metric, metricLabel, value, max }: GaugeData, theme: ChartTheme, color: string): EChartsOption {
    return {
        animationDuration: 600,
        series: [
            {
                type: 'gauge',
                name: metricLabel,
                min: 0,
                max,
                startAngle: 205,
                endAngle: -25,
                center: ['50%', '62%'],
                radius: '88%',
                splitNumber: 1,
                progress: { show: true, width: 16, roundCap: true, itemStyle: { color } },
                axisLine: { roundCap: true, lineStyle: { width: 16, color: [[1, theme.grid]] } },
                pointer: { show: false },
                axisTick: { show: false },
                splitLine: { show: false },
                // Only the two ends are labelled: the big number says where the value stands.
                axisLabel: {
                    distance: 22,
                    color: theme.muted,
                    fontSize: 11,
                    formatter: (tick: number) => formatValue(tick, metric?.format, { compact: true }),
                },
                anchor: { show: false },
                title: { show: true, offsetCenter: [0, '26%'], color: theme.muted, fontSize: 12 },
                detail: {
                    valueAnimation: true,
                    offsetCenter: [0, '-4%'],
                    fontSize: 30,
                    fontWeight: 600,
                    color: theme.text,
                    formatter: (shown: number) => formatValue(shown, metric?.format, { compact: true }),
                },
                data: [{ value, name: `de ${formatValue(max, metric?.format, { compact: true })}` }],
            },
        ],
    };
}
