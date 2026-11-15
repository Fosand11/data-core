import { lightThemeFor, type ChartTheme } from '@/composables/useChartTheme';
import { appName } from '@/lib/brand';
import { renderChartImage } from '@/lib/echarts';
import { formatCell, formatValue } from '@/lib/format';
import { describeFilter, labelFor } from '@/lib/labels';
import { fitText, planPages, rowHeightFor, spanHeight } from '@/lib/sheetLayout';
import type { DashboardSpec, DashboardVisualization, ExportContext, VisualizationColumn, VisualizationData, VisualizationResult } from '@/types';

/**
 * Draws the whole dashboard onto sheets (canvases) the way it looks on screen: the same
 * 12-column layout, one card per visualization. A dashboard that is too tall for one sheet
 * continues on the next one, always between rows so no card is cut. The PDF is made of
 * these sheets and the PowerPoint opens with them.
 */

export interface SheetOptions {
    /** Width over height of every sheet: 16:9 for slides, about 1.41 for A4 landscape. */
    aspect: number;
    /** Layout width in CSS pixels. */
    width?: number;
    /** Pixel density of the result; 2 keeps text sharp when zoomed or printed. */
    scale?: number;
}

const FONT = '-apple-system, BlinkMacSystemFont, "SF Pro Text", Inter, "Segoe UI", sans-serif';
const PAGE = '#F3F5F2';
const PANEL = '#F3F5F2';

const MARGIN = 40;
const GAP = 16;
const ROW_MAX = 72;
const HEADER_FIRST = 140;
const HEADER_NEXT = 92;
const FOOTER = 44;
const BOTTOM = 12;
const CARD_PAD = 20;
const CARD_TITLE = 48;
const TABLE_HEAD = 28;
const TABLE_ROW = 26;

const CHART_TYPES: DashboardVisualization['type'][] = ['bar', 'hbar', 'line', 'area', 'pie', 'funnel', 'treemap', 'radar', 'scatter', 'gauge'];

export const isChartType = (visualization: DashboardVisualization) => CHART_TYPES.includes(visualization.type);

/** Table visualizations follow their configured columns; anything else without a plot shows every column. */
export function tableColumns(visualization: DashboardVisualization, result: VisualizationResult | undefined): VisualizationColumn[] {
    if (!result) return [];

    const configured = visualization.display.columns;

    if (visualization.type === 'table' && Array.isArray(configured)) {
        return configured
            .map((key) => result.columns.find((column) => column.key === key))
            .filter((column): column is VisualizationColumn => column !== undefined);
    }

    return result.columns;
}

const columnTitle = (column: VisualizationColumn) => (column.type === 'month' ? 'Mes' : labelFor(column.key));

type Context2D = CanvasRenderingContext2D;

function roundedRect(ctx: Context2D, x: number, y: number, w: number, h: number, radius: number) {
    const r = Math.min(radius, w / 2, h / 2);

    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

const font = (size: number, weight = 400) => `${weight} ${size}px ${FONT}`;

function text(
    ctx: Context2D,
    value: string,
    x: number,
    y: number,
    options: { size: number; weight?: number; color: string; align?: CanvasTextAlign; maxWidth?: number },
) {
    ctx.font = font(options.size, options.weight);
    ctx.fillStyle = options.color;
    ctx.textAlign = options.align ?? 'left';
    ctx.textBaseline = 'alphabetic';

    const shown = options.maxWidth === undefined ? value : fitText(value, options.maxWidth, (candidate) => ctx.measureText(candidate).width);

    ctx.fillText(shown, x, y);
}

/** The small mark of the app, drawn with the same shapes as the favicon. */
function drawLogo(ctx: Context2D, x: number, y: number, size: number) {
    const k = size / 32;

    ctx.fillStyle = '#1F6B51';
    roundedRect(ctx, x, y, size, size, 7.5 * k);
    ctx.fill();

    ctx.strokeStyle = '#FFFFFF';
    ctx.lineWidth = 2.8 * k;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(x + 8.2 * k, y + 23.4 * k);
    ctx.lineTo(x + 16 * k, y + 8.6 * k);
    ctx.lineTo(x + 23.8 * k, y + 23.4 * k);
    ctx.stroke();

    ctx.fillStyle = '#F2B53A';
    ctx.beginPath();
    ctx.arc(x + 16 * k, y + 19.4 * k, 2.3 * k, 0, Math.PI * 2);
    ctx.fill();
}

async function loadImage(url: string): Promise<HTMLImageElement> {
    const image = new Image();

    image.src = url;
    await image.decode();

    return image;
}

interface Box {
    x: number;
    y: number;
    w: number;
    h: number;
}

interface Palette {
    theme: ChartTheme;
    ink: string;
    muted: string;
    border: string;
    surface: string;
}

async function drawCard(
    ctx: Context2D,
    visualization: DashboardVisualization,
    result: VisualizationResult | undefined,
    box: Box,
    palette: Palette,
    export_: { accent: number | undefined; scale: number },
) {
    ctx.fillStyle = palette.surface;
    roundedRect(ctx, box.x, box.y, box.w, box.h, 16);
    ctx.fill();
    ctx.strokeStyle = palette.border;
    ctx.lineWidth = 1;
    roundedRect(ctx, box.x + 0.5, box.y + 0.5, box.w - 1, box.h - 1, 16);
    ctx.stroke();

    const innerW = box.w - CARD_PAD * 2;
    text(ctx, visualization.title, box.x + CARD_PAD, box.y + 30, { size: 15, weight: 600, color: palette.ink, maxWidth: innerW });

    const filters = visualization.query.filters.map(describeFilter).join(' · ');
    const inner: Box = { x: box.x + CARD_PAD, y: box.y + CARD_TITLE, w: innerW, h: box.h - CARD_TITLE - CARD_PAD + 4 };

    if (filters) {
        text(ctx, filters, box.x + CARD_PAD, box.y + 44, { size: 11, color: palette.muted, maxWidth: innerW });
        inner.y += 8;
        inner.h -= 8;
    }

    if (!result) {
        text(ctx, 'Sin datos cargados.', inner.x, inner.y + 20, { size: 13, color: palette.muted });

        return;
    }

    if (visualization.type === 'kpi') {
        drawKpi(ctx, result, inner, palette);

        return;
    }

    if (result.rows.length === 0 && visualization.type !== 'table') {
        text(ctx, 'Sin datos para los filtros de este dashboard.', inner.x, inner.y + 20, { size: 13, color: palette.muted });

        return;
    }

    if (isChartType(visualization)) {
        const url = renderChartImage(visualization, result, {
            width: Math.max(80, Math.round(inner.w)),
            height: Math.max(60, Math.round(inner.h)),
            theme: palette.theme,
            accent: export_.accent,
            pixelRatio: export_.scale,
        });

        ctx.drawImage(await loadImage(url), inner.x, inner.y, inner.w, inner.h);

        return;
    }

    drawTable(ctx, visualization, result, inner, palette);
}

function drawKpi(ctx: Context2D, result: VisualizationResult, inner: Box, palette: Palette) {
    const metric = result.columns.find((column) => column.role === 'metric');
    const value = metric ? (result.rows[0]?.[metric.key] ?? null) : null;
    const display = formatValue(value, metric?.format, { compact: Math.abs(Number(value)) >= 1_000_000 });
    let size = 40;

    ctx.font = font(size, 600);

    while (size > 16 && ctx.measureText(display).width > inner.w) {
        size -= 2;
        ctx.font = font(size, 600);
    }

    text(ctx, display, inner.x, inner.y + Math.min(inner.h, 64) / 2 + size / 3, { size, weight: 600, color: palette.ink });
}

function drawTable(ctx: Context2D, visualization: DashboardVisualization, result: VisualizationResult, inner: Box, palette: Palette) {
    const columns = tableColumns(visualization, result);

    if (columns.length === 0) return;

    const weights = columns.map((column) => (column.role === 'metric' ? 1 : 1.5));
    const total = weights.reduce((sum, weight) => sum + weight, 0);
    const widths = weights.map((weight) => (inner.w * weight) / total);
    const capacity = Math.max(1, Math.floor((inner.h - TABLE_HEAD) / TABLE_ROW));
    const overflow = result.rows.length > capacity;
    // When rows are cut, the last line says how many were left out.
    const shown = overflow ? result.rows.slice(0, Math.max(1, capacity - 1)) : result.rows;
    const cellX = (index: number) => inner.x + widths.slice(0, index).reduce((sum, width) => sum + width, 0);
    const pad = 10;

    ctx.fillStyle = PANEL;
    roundedRect(ctx, inner.x, inner.y, inner.w, TABLE_HEAD, 8);
    ctx.fill();

    columns.forEach((column, index) => {
        const right = column.role === 'metric';

        text(ctx, columnTitle(column), right ? cellX(index) + widths[index] - pad : cellX(index) + pad, inner.y + 18, {
            size: 11,
            weight: 600,
            color: palette.muted,
            align: right ? 'right' : 'left',
            maxWidth: widths[index] - pad * 2,
        });
    });

    shown.forEach((row, rowIndex) => {
        const top = inner.y + TABLE_HEAD + rowIndex * TABLE_ROW;

        ctx.strokeStyle = palette.border;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(inner.x, top + 0.5);
        ctx.lineTo(inner.x + inner.w, top + 0.5);
        ctx.stroke();

        columns.forEach((column, index) => {
            const right = column.role === 'metric';

            text(
                ctx,
                formatCell(row[column.key], column, result.meta?.current_month),
                right ? cellX(index) + widths[index] - pad : cellX(index) + pad,
                top + 18,
                {
                    size: 12,
                    color: palette.ink,
                    align: right ? 'right' : 'left',
                    maxWidth: widths[index] - pad * 2,
                },
            );
        });
    });

    if (overflow) {
        const top = inner.y + TABLE_HEAD + shown.length * TABLE_ROW;

        text(ctx, `… y ${result.rows.length - shown.length} filas más (descarga el CSV para verlas todas)`, inner.x + pad, top + 18, {
            size: 11,
            color: palette.muted,
            maxWidth: inner.w - pad * 2,
        });
    }
}

function drawHeader(ctx: Context2D, spec: DashboardSpec, context: ExportContext, width: number, first: boolean, palette: Palette) {
    const dataset = context.datasetName ?? 'Datos de ejemplo';

    if (first) {
        text(ctx, context.title, MARGIN, MARGIN + 28, { size: 30, weight: 700, color: palette.ink, maxWidth: width - MARGIN * 2 - 180 });

        if (context.description) {
            text(ctx, context.description, MARGIN, MARGIN + 54, { size: 14, color: palette.muted, maxWidth: width - MARGIN * 2 - 180 });
        }

        text(ctx, `${context.periodLabel}  ·  ${dataset}`, MARGIN, MARGIN + 76, {
            size: 13,
            color: palette.muted,
            maxWidth: width - MARGIN * 2 - 180,
        });
    } else {
        text(ctx, `${context.title} (continuación)`, MARGIN, MARGIN + 18, {
            size: 18,
            weight: 600,
            color: palette.ink,
            maxWidth: width - MARGIN * 2 - 180,
        });
    }

    // The app's mark at the right, with its name.
    const name = appName();

    ctx.font = font(14, 600);
    const nameWidth = ctx.measureText(name).width;

    drawLogo(ctx, width - MARGIN - nameWidth - 34, MARGIN + 4, 24);
    text(ctx, name, width - MARGIN, MARGIN + 22, { size: 14, weight: 600, color: palette.ink, align: 'right' });

    void spec;
}

function drawFooter(ctx: Context2D, context: ExportContext, width: number, height: number, page: number, pages: number, palette: Palette) {
    const y = height - FOOTER + 16;

    ctx.strokeStyle = palette.border;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(MARGIN, height - FOOTER + 0.5);
    ctx.lineTo(width - MARGIN, height - FOOTER + 0.5);
    ctx.stroke();

    const date = new Intl.DateTimeFormat('es', { dateStyle: 'long' }).format(new Date());

    text(ctx, `${appName()}  ·  ${context.datasetName ?? 'Datos de ejemplo'}  ·  ${date}`, MARGIN, y + 8, {
        size: 11,
        color: palette.muted,
        maxWidth: width - MARGIN * 2 - 120,
    });
    text(ctx, `Página ${page} de ${pages}`, width - MARGIN, y + 8, { size: 11, color: palette.muted, align: 'right' });
}

/**
 * One canvas per sheet, in order. Charts are drawn with the dashboard's palette in the light
 * theme, so a file looks the same whatever theme the author was viewing.
 */
export async function renderDashboardSheets(
    spec: DashboardSpec,
    data: VisualizationData,
    context: ExportContext,
    options: SheetOptions,
): Promise<HTMLCanvasElement[]> {
    const width = options.width ?? 1600;
    const scale = options.scale ?? 2;
    const height = Math.round(width / options.aspect);
    const theme = lightThemeFor(context.palette);
    const palette: Palette = { theme, ink: theme.text, muted: theme.muted, border: theme.grid, surface: theme.surface };

    // Pages are planned for the first page, which has the tallest header, so none overflows.
    const availableFirst = height - HEADER_FIRST - FOOTER - BOTTOM;
    const capacity = Math.max(1, Math.floor((availableFirst + GAP) / (ROW_MAX + GAP)));
    const cards = spec.visualizations.map((visualization) => ({ y: visualization.layout.y, height: visualization.layout.height, visualization }));
    const plans = planPages(cards, capacity);
    const colWidth = (width - MARGIN * 2 - GAP * 11) / 12;
    const sheets: HTMLCanvasElement[] = [];

    for (const [index, plan] of plans.entries()) {
        const first = index === 0;
        const canvas = document.createElement('canvas');

        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const ctx = canvas.getContext('2d');

        if (ctx === null) throw new Error('Canvas is not available.');

        ctx.scale(scale, scale);
        ctx.fillStyle = PAGE;
        ctx.fillRect(0, 0, width, height);

        drawHeader(ctx, spec, context, width, first, palette);

        const top = first ? HEADER_FIRST : HEADER_NEXT;
        const available = height - top - FOOTER - BOTTOM;
        const rows = Math.max(1, plan.to - plan.from);
        const rowPx = rowHeightFor(rows, available, GAP, ROW_MAX);

        for (const card of cards.filter((item) => item.y >= plan.from && item.y < plan.to)) {
            const layout = card.visualization.layout;

            await drawCard(
                ctx,
                card.visualization,
                data[card.visualization.key],
                {
                    x: MARGIN + layout.x * (colWidth + GAP),
                    y: top + (layout.y - plan.from) * (rowPx + GAP),
                    w: layout.width * colWidth + (layout.width - 1) * GAP,
                    h: spanHeight(layout.height, rowPx, GAP),
                },
                palette,
                { accent: context.colors?.[card.visualization.key], scale },
            );
        }

        drawFooter(ctx, context, width, height, index + 1, plans.length, palette);
        sheets.push(canvas);
    }

    return sheets;
}
