import type { VisualizationResult } from '@/types';
import { describe, expect, it } from 'vitest';
import { csvCell, exportFilename, slugify, toCsv } from './export';

describe('slugify', () => {
    it('removes accents, punctuation and case', () => {
        expect(slugify('Ventas por Región (Q4)')).toBe('ventas-por-region-q4');
        expect(slugify('  ¿Qué pasó?  ')).toBe('que-paso');
    });

    it('never produces path separators or dots, whatever the title says', () => {
        expect(slugify('../../etc/passwd')).toBe('etc-passwd');
        expect(slugify('a/b\\c:d*e?.exe')).toBe('a-b-c-d-e-exe');
    });

    it('falls back when nothing usable is left and caps the length', () => {
        expect(slugify('???')).toBe('dashboard');
        expect(slugify('???', 'datos')).toBe('datos');
        expect(slugify('a'.repeat(200)).length).toBeLessThanOrEqual(60);
        expect(slugify('ab '.repeat(40)).endsWith('-')).toBe(false);
    });
});

describe('exportFilename', () => {
    it('joins the readable parts and the date', () => {
        expect(exportFilename(['Ventas por región', 'Gráfico: barras'], 'png', new Date('2026-11-12T10:00:00Z'))).toBe(
            'ventas-por-region-grafico-barras-2026-11-12.png',
        );
    });

    it('does not repeat a part that is identical to an earlier one', () => {
        expect(exportFilename(['Ventas por región', 'Ventas por Región'], 'csv', new Date('2026-11-12T10:00:00Z'))).toBe(
            'ventas-por-region-2026-11-12.csv',
        );
    });

    it('drops empty parts and still names the file', () => {
        expect(exportFilename(['???', ''], 'csv', new Date('2026-01-05T00:00:00Z'))).toBe('dashboard-2026-01-05.csv');
    });
});

describe('csvCell', () => {
    it('leaves plain text and numbers alone', () => {
        expect(csvCell('Norte')).toBe('Norte');
        expect(csvCell(1234.5)).toBe('1234.5');
        expect(csvCell(-20)).toBe('-20');
    });

    it('renders empty values as empty cells', () => {
        expect(csvCell(null)).toBe('');
        expect(csvCell(undefined)).toBe('');
        expect(csvCell(Number.NaN)).toBe('');
        expect(csvCell(Infinity)).toBe('');
    });

    it('quotes commas, quotes and line breaks', () => {
        expect(csvCell('a,b')).toBe('"a,b"');
        expect(csvCell('di "hola"')).toBe('"di ""hola"""');
        expect(csvCell('línea 1\nlínea 2')).toBe('"línea 1\nlínea 2"');
    });

    it.each(['=SUM(A1:A9)', '+cmd|calc', '-2+3', '@SUM(A1)', '\t=1+1', '\r=1+1'])('neutralises the spreadsheet formula %j', (value) => {
        const cell = csvCell(value);

        expect(cell.startsWith("'") || cell.startsWith('"\'')).toBe(true);
    });

    it('neutralises a formula that also needs quoting', () => {
        expect(csvCell('=HYPERLINK("http://evil","x")')).toBe('"\'=HYPERLINK(""http://evil"",""x"")"');
    });

    it('does not touch numbers that merely look negative', () => {
        expect(csvCell(-5)).toBe('-5');
    });
});

describe('toCsv', () => {
    const result: VisualizationResult = {
        columns: [
            { key: 'sold_at', role: 'dimension', type: 'month', format: null },
            { key: 'region', role: 'dimension', type: 'string', format: null },
            { key: 'total_sales', role: 'metric', type: 'number', format: 'currency' },
        ],
        rows: [
            { sold_at: '2026-10', region: 'Norte', total_sales: 1200.5 },
            { sold_at: '2026-11', region: '=cmd()', total_sales: null },
        ],
    };

    it('writes Spanish headers, raw values and a BOM for Excel', () => {
        const csv = toCsv(result);

        expect(csv.startsWith('﻿')).toBe(true);
        expect(csv).toBe("﻿Mes,Región,Ventas totales\r\n2026-10,Norte,1200.5\r\n2026-11,'=cmd(),\r\n");
    });

    it('can limit and reorder the columns', () => {
        expect(toCsv(result, ['total_sales', 'region'])).toBe("﻿Ventas totales,Región\r\n1200.5,Norte\r\n,'=cmd()\r\n");
    });

    it('ignores unknown columns instead of failing', () => {
        expect(toCsv(result, ['region', 'nope'])).toBe("﻿Región\r\nNorte\r\n'=cmd()\r\n");
    });

    it('produces just the header for an empty result', () => {
        expect(toCsv({ ...result, rows: [] })).toBe('﻿Mes,Región,Ventas totales\r\n');
    });
});
