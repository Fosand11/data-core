import type { ChartTheme } from '@/composables/useChartTheme';
import { IN_PROGRESS_SUFFIX, formatDimension, formatValue, isCurrentMonth } from '@/lib/format';
import { labelFor } from '@/lib/labels';
import type { DashboardVisualization, VisualizationColumn, VisualizationResult } from '@/types';
import type { EChartsOption } from 'echarts';

/** The validated categorical palette has six slots; pie slices never go beyond it. */
export const MAX_PIE_SLICES = 6;

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
    const categoryKey = String(visualization.type === 'pie' ? display.category : display.x_axis);
    const valueKey = String(visualization.type === 'pie' ? display.value : display.y_axis);
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
    return visualization.type !== 'pie' && chartData(visualization, result).points.some((point) => point.partial);
}

/** The axis shows a compact marker; the full "(en curso)" text stays in tooltips and tables. */
const axisLabel = (label: string) => (label.endsWith(IN_PROGRESS_SUFFIX) ? `${label.slice(0, -IN_PROGRESS_SUFFIX.length)}*` : label);

/** One-sentence text alternative of a chart, for assistive technology. */
export function describeChart(visualization: DashboardVisualization, result: VisualizationResult): string {
    const { metric, metricLabel, points } = chartData(visualization, result);
    const kinds: Partial<Record<DashboardVisualization['type'], string>> = {
        bar: 'Gráfico de barras',
        line: 'Gráfico de línea',
        area: 'Gráfico de área',
        pie: 'Gráfico circular',
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

export function buildChartOption(visualization: DashboardVisualization, result: VisualizationResult, theme: ChartTheme): EChartsOption {
    const data = chartData(visualization, result);

    switch (visualization.type) {
        case 'bar':
            return barOption(data, theme);
        case 'line':
        case 'area':
            return trendOption(data, theme, visualization.type === 'area');
        case 'pie':
            return pieOption(data, theme);
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

function barOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme): EChartsOption {
    // Long names or many categories read better as horizontal bars.
    const horizontal = points.length > 5 || points.some((point) => point.label.length > 12);
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
                itemStyle: { color: theme.series[0], borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0] },
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

function trendOption({ metric, metricLabel, points }: ChartSeriesData, theme: ChartTheme, filled: boolean): EChartsOption {
    const color = theme.series[0];

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
