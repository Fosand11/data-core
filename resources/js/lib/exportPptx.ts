import { LIGHT_CHART_THEME } from '@/composables/useChartTheme';
import { describeChart } from '@/lib/chartOptions';
import { formatCell, formatValue } from '@/lib/format';
import { describeFilter, labelFor } from '@/lib/labels';
import type { DashboardSpec, DashboardVisualization, ExportContext, VisualizationColumn, VisualizationData, VisualizationResult } from '@/types';

const FONT = 'Arial';
const INK = '14211C';
const MUTED = '5A6962';
const PINE = '1F6B52';
const PANEL = 'F3F5F2';
const BORDER = 'DDE3DE';

const KPIS_PER_SLIDE = 6;
const TABLE_ROWS_PER_SLIDE = 13;
const CHART_IMAGE = { width: 1480, height: 636 };

const isChart = (visualization: DashboardVisualization) => ['bar', 'line', 'area', 'pie'].includes(visualization.type);
const columnTitle = (column: VisualizationColumn) => (column.type === 'month' ? 'Mes' : labelFor(column.key));

/** Layout order: top to bottom, then left to right, as the dashboard reads on screen. */
const inReadingOrder = (visualizations: DashboardVisualization[]) =>
    [...visualizations].sort((a, b) => a.layout.y - b.layout.y || a.layout.x - b.layout.x);

const chunk = <T>(items: T[], size: number): T[][] => {
    const groups: T[][] = [];

    for (let index = 0; index < items.length; index += size) groups.push(items.slice(index, index + size));

    return groups.length ? groups : [[]];
};

/**
 * Builds a 16:9 PowerPoint deck from the dashboard as the viewer sees it, for
 * the period that is selected. Charts are exported as images drawn with the
 * light theme (they look like the app and never depend on dark mode), while
 * tables and numbers stay native so they can be edited in PowerPoint.
 */
export async function buildDeck(spec: DashboardSpec, data: VisualizationData, context: ExportContext): Promise<Blob> {
    const [{ default: PptxGenJS }, { renderChartImage }] = await Promise.all([import('pptxgenjs'), import('@/lib/echarts')]);
    const pptx = new PptxGenJS();

    pptx.layout = 'LAYOUT_WIDE';
    pptx.title = context.title;
    pptx.author = 'InsightFlow';
    pptx.company = 'InsightFlow';

    pptx.defineSlideMaster({
        title: 'CONTENT',
        background: { color: 'FFFFFF' },
        objects: [
            { line: { x: 0.5, y: 6.95, w: 12.33, h: 0, line: { color: BORDER, width: 0.75 } } },
            {
                text: {
                    text: 'InsightFlow · Datos de ejemplo',
                    options: { x: 0.5, y: 7.02, w: 6, h: 0.3, fontFace: FONT, fontSize: 10, color: MUTED },
                },
            },
        ],
        slideNumber: { x: 12.33, y: 7.02, w: 0.5, h: 0.3, fontFace: FONT, fontSize: 10, color: MUTED, align: 'right' },
    });

    const generated = new Intl.DateTimeFormat('es', { dateStyle: 'long' }).format(new Date());

    // --- Cover
    const cover = pptx.addSlide();
    cover.background = { color: '10201A' };
    cover.addText(context.title, { x: 0.8, y: 2.0, w: 11.7, h: 1.6, fontFace: FONT, fontSize: 40, bold: true, color: 'FFFFFF', valign: 'bottom' });
    if (context.description) {
        cover.addText(context.description, { x: 0.8, y: 3.7, w: 11.0, h: 1.0, fontFace: FONT, fontSize: 18, color: 'B7C7BE', valign: 'top' });
    }
    cover.addText([{ text: context.periodLabel }, { text: `  ·  ${generated}  ·  Datos de ejemplo` }], {
        x: 0.8,
        y: 5.3,
        w: 11.7,
        h: 0.5,
        fontFace: FONT,
        fontSize: 14,
        color: '4FCB9B',
    });
    cover.addText('InsightFlow', { x: 0.8, y: 6.7, w: 4, h: 0.4, fontFace: FONT, fontSize: 12, bold: true, color: 'FFFFFF' });

    const ordered = inReadingOrder(spec.visualizations);

    // --- Key figures: every KPI on shared slides
    const kpis = ordered.filter((visualization) => visualization.type === 'kpi');

    chunk(kpis, KPIS_PER_SLIDE).forEach((group, page, pages) => {
        if (group.length === 0) return;

        const slide = pptx.addSlide({ masterName: 'CONTENT' });
        slide.addText(pages.length > 1 ? `Indicadores clave (${page + 1}/${pages.length})` : 'Indicadores clave', {
            x: 0.5,
            y: 0.4,
            w: 12.33,
            h: 0.7,
            fontFace: FONT,
            fontSize: 28,
            bold: true,
            color: INK,
        });
        slide.addText(context.periodLabel, { x: 0.5, y: 1.05, w: 12.33, h: 0.4, fontFace: FONT, fontSize: 14, color: MUTED });

        const cardWidth = (12.33 - 0.4 * 2) / 3;

        group.forEach((visualization, index) => {
            const result = data[visualization.key];
            const metric = result?.columns.find((column) => column.role === 'metric');
            const value = metric ? (result?.rows[0]?.[metric.key] ?? null) : null;
            const x = 0.5 + (index % 3) * (cardWidth + 0.4);
            const y = 1.8 + Math.floor(index / 3) * 2.4;

            slide.addShape('roundRect', {
                x,
                y,
                w: cardWidth,
                h: 2.0,
                fill: { color: PANEL },
                line: { color: BORDER, width: 0.75 },
                rectRadius: 0.12,
            });
            slide.addText(visualization.title, {
                x: x + 0.25,
                y: y + 0.2,
                w: cardWidth - 0.5,
                h: 0.5,
                fontFace: FONT,
                fontSize: 14,
                color: MUTED,
                valign: 'top',
            });
            slide.addText(formatValue(value, metric?.format), {
                x: x + 0.25,
                y: y + 0.8,
                w: cardWidth - 0.5,
                h: 1.0,
                fontFace: FONT,
                fontSize: 36,
                bold: true,
                color: INK,
                valign: 'middle',
                fit: 'shrink',
            });
        });
    });

    // --- Charts and tables, in reading order
    for (const visualization of ordered) {
        const result: VisualizationResult | undefined = data[visualization.key];

        if (visualization.type === 'kpi') continue;

        const filters = visualization.query.filters.map(describeFilter).join(' · ');
        const subtitle = [filters, context.periodLabel].filter(Boolean).join('  ·  ');

        if (isChart(visualization) && result && result.rows.length > 0) {
            const slide = pptx.addSlide({ masterName: 'CONTENT' });
            slide.addText(visualization.title, { x: 0.5, y: 0.4, w: 12.33, h: 0.7, fontFace: FONT, fontSize: 28, bold: true, color: INK });
            slide.addText(subtitle, { x: 0.5, y: 1.05, w: 12.33, h: 0.4, fontFace: FONT, fontSize: 14, color: MUTED });
            slide.addImage({
                data: renderChartImage(visualization, result, { ...CHART_IMAGE, theme: LIGHT_CHART_THEME, pixelRatio: 2 }),
                x: 0.5,
                y: 1.55,
                w: 12.33,
                h: 5.3,
                altText: describeChart(visualization, result),
            });

            continue;
        }

        const columns = tableColumns(visualization, result);

        if (!result || columns.length === 0) continue;

        const pages = chunk(result.rows, TABLE_ROWS_PER_SLIDE);

        pages.forEach((rows, page) => {
            const slide = pptx.addSlide({ masterName: 'CONTENT' });
            slide.addText(pages.length > 1 ? `${visualization.title} (${page + 1}/${pages.length})` : visualization.title, {
                x: 0.5,
                y: 0.4,
                w: 12.33,
                h: 0.7,
                fontFace: FONT,
                fontSize: 28,
                bold: true,
                color: INK,
            });
            slide.addText(subtitle, { x: 0.5, y: 1.05, w: 12.33, h: 0.4, fontFace: FONT, fontSize: 14, color: MUTED });

            if (rows.length === 0) {
                slide.addText('No hay datos para este periodo.', { x: 0.5, y: 2.0, w: 12.33, h: 0.6, fontFace: FONT, fontSize: 18, color: MUTED });

                return;
            }

            const head = columns.map((column) => ({
                text: columnTitle(column),
                options: {
                    bold: true,
                    color: 'FFFFFF',
                    fill: { color: PINE },
                    align: column.role === 'metric' ? ('right' as const) : ('left' as const),
                },
            }));
            const body = rows.map((row) =>
                columns.map((column) => ({
                    text: formatCell(row[column.key], column, result.meta?.current_month),
                    options: { color: INK, align: column.role === 'metric' ? ('right' as const) : ('left' as const) },
                })),
            );

            slide.addTable([head, ...body], {
                x: 0.5,
                y: 1.6,
                w: 12.33,
                fontFace: FONT,
                fontSize: 12,
                rowH: 0.36,
                border: { type: 'solid', color: BORDER, pt: 0.75 },
                margin: [0.04, 0.12, 0.04, 0.12],
            });
        });
    }

    return (await pptx.write({ outputType: 'blob' })) as Blob;
}

/** Table visualizations follow their configured columns; charts without a drawable plot fall back to every column. */
function tableColumns(visualization: DashboardVisualization, result: VisualizationResult | undefined): VisualizationColumn[] {
    if (!result) return [];

    const configured = visualization.display.columns;

    if (visualization.type === 'table' && Array.isArray(configured)) {
        return configured
            .map((key) => result.columns.find((column) => column.key === key))
            .filter((column): column is VisualizationColumn => column !== undefined);
    }

    return result.columns;
}
