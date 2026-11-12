import { labelFor } from '@/lib/labels';
import type { VisualizationColumn, VisualizationResult } from '@/types';

/** "Ventas por Región (Q4)" -> "ventas-por-region-q4": safe for any file system and readable. */
export function slugify(text: string, fallback = 'dashboard'): string {
    const slug = text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60)
        .replace(/-+$/g, '');

    return slug === '' ? fallback : slug;
}

/** "ventas-por-region-grafico-2026-11-12.png" */
export function exportFilename(parts: string[], extension: string, date: Date = new Date()): string {
    const day = date.toISOString().slice(0, 10);
    // A chart named like its dashboard would otherwise repeat the same words in the file name.
    const name = parts.map((part) => slugify(part, '')).filter((part, index, all) => part !== '' && all.indexOf(part) === index);

    return `${[...(name.length ? name : ['dashboard']), day].join('-')}.${extension}`;
}

const FORMULA_START = /^[=+\-@\t\r]/;

/**
 * One CSV cell. Text that a spreadsheet could run as a formula ("=", "+", "-",
 * "@") is prefixed with an apostrophe so it stays text (CSV injection), and
 * anything with a delimiter, quote or line break is quoted.
 */
export function csvCell(value: string | number | null | undefined): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'number') return Number.isFinite(value) ? String(value) : '';

    const safe = FORMULA_START.test(value) ? `'${value}` : value;

    return /[",\r\n]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
}

const header = (column: VisualizationColumn) => (column.type === 'month' ? 'Mes' : labelFor(column.key));

/**
 * The query result as CSV: raw values (numbers unformatted, months as YYYY-MM)
 * so it can be analysed further. A BOM makes Excel read it as UTF-8.
 */
export function toCsv(result: VisualizationResult, only?: string[]): string {
    const columns = only?.length
        ? only
              .map((key) => result.columns.find((column) => column.key === key))
              .filter((column): column is VisualizationColumn => column !== undefined)
        : result.columns;

    const lines = [
        columns.map((column) => csvCell(header(column))).join(','),
        ...result.rows.map((row) => columns.map((column) => csvCell(row[column.key])).join(',')),
    ];

    return `﻿${lines.join('\r\n')}\r\n`;
}

export function downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);

    downloadUrl(url, filename);
    // Give the browser time to start the download before releasing the object URL.
    setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

export function downloadUrl(url: string, filename: string): void {
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
}

export interface ImageFrame {
    title: string;
    subtitle?: string;
    footer?: string;
}

/**
 * Wraps a chart image in a titled frame so a shared picture explains itself:
 * what it shows, for which period, and where it comes from.
 */
export async function composeImage(chartDataUrl: string, frame: ImageFrame): Promise<string> {
    const image = new Image();
    image.src = chartDataUrl;
    await image.decode();

    const scale = 2;
    const pad = 32 * scale;
    const headerHeight = (frame.subtitle ? 76 : 52) * scale;
    const footerHeight = frame.footer ? 40 * scale : 16 * scale;
    const canvas = document.createElement('canvas');
    canvas.width = image.width + pad * 2;
    canvas.height = image.height + headerHeight + footerHeight;

    const context = canvas.getContext('2d');
    if (context === null) throw new Error('Canvas is not available.');

    const font = '-apple-system, BlinkMacSystemFont, "SF Pro Text", Inter, "Segoe UI", sans-serif';
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.fillStyle = '#14211C';
    context.font = `600 ${26 * scale}px ${font}`;
    context.textBaseline = 'top';
    context.fillText(frame.title, pad, 24 * scale, canvas.width - pad * 2);

    if (frame.subtitle) {
        context.fillStyle = '#5A6962';
        context.font = `400 ${15 * scale}px ${font}`;
        context.fillText(frame.subtitle, pad, 56 * scale, canvas.width - pad * 2);
    }

    context.drawImage(image, pad, headerHeight);

    if (frame.footer) {
        context.fillStyle = '#5A6962';
        context.font = `400 ${12 * scale}px ${font}`;
        context.fillText(frame.footer, pad, headerHeight + image.height + 12 * scale, canvas.width - pad * 2);
    }

    return canvas.toDataURL('image/png');
}
