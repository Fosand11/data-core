import { describe, expect, it } from 'vitest';
import { A4_LANDSCAPE, buildPdf } from './pdf';

const decode = (bytes: Uint8Array) => new TextDecoder('latin1').decode(bytes);
// Not a real picture: the writer only wraps bytes, so any bytes prove the container is right.
const fakeJpeg = (size: number) => Uint8Array.from({ length: size }, (_, index) => index % 251);

const options = { ...A4_LANDSCAPE, title: 'Ventas por región' };

describe('buildPdf', () => {
    it('writes a PDF 1.4 file that ends where it says it does', () => {
        const file = buildPdf([{ jpeg: fakeJpeg(1000), width: 3200, height: 2262 }], options);
        const text = decode(file);

        expect(text.startsWith('%PDF-1.4\n')).toBe(true);
        expect(text.endsWith('%%EOF\n')).toBe(true);
        expect(text).toContain('/Type /Catalog');
    });

    it('lists one page per picture, in order', () => {
        const file = buildPdf(
            [1, 2, 3].map((n) => ({ jpeg: fakeJpeg(200 * n), width: 100, height: 70 })),
            options,
        );
        const text = decode(file);

        expect(text).toContain('/Count 3');
        expect(text.match(/\/Type \/Page /g)).toHaveLength(3);
        expect(text).toContain('/Kids [4 0 R 7 0 R 10 0 R]');
    });

    it('points every cross-reference entry at the start of its object', () => {
        const file = buildPdf(
            [1, 2].map((n) => ({ jpeg: fakeJpeg(333 * n), width: 100, height: 70 })),
            options,
        );
        const text = decode(file);
        const startxref = Number(/startxref\n(\d+)\n%%EOF/.exec(text)?.[1]);

        expect(text.slice(startxref, startxref + 4)).toBe('xref');

        const entries = [...text.slice(startxref).matchAll(/^(\d{10}) \d{5} n $/gm)].map((match) => Number(match[1]));

        // catalog, page tree, info and three objects for each of the two pages
        expect(entries).toHaveLength(9);
        entries.forEach((offset, index) => {
            expect(text.slice(offset, offset + `${index + 1} 0 obj`.length)).toBe(`${index + 1} 0 obj`);
        });
    });

    it('declares the right size for the stream so viewers can find where the picture ends', () => {
        const jpeg = fakeJpeg(777);
        const file = buildPdf([{ jpeg, width: 640, height: 450 }], options);
        const text = decode(file);

        expect(text).toContain(`/Width 640 /Height 450`);
        expect(text).toContain('/Filter /DCTDecode /Length 777 ');

        const start = text.indexOf('stream\n', text.indexOf('/Subtype /Image')) + 'stream\n'.length;

        expect([...file.slice(start, start + 777)]).toEqual([...jpeg]);
        expect(text.slice(start + 777, start + 777 + 10)).toBe('\nendstream');
    });

    it('stores the title in a form that keeps accents', () => {
        const text = decode(buildPdf([{ jpeg: fakeJpeg(10), width: 10, height: 10 }], options));
        // UTF-16BE with a byte-order mark: "Ventas por región" -> FEFF 0056 0065 ... 00f3 (ó) 006e.
        const expected = [...options.title].reduce((hex, char) => hex + char.charCodeAt(0).toString(16).padStart(4, '0'), 'FEFF');

        expect(text).toContain(`/Title <${expected}>`);
        expect(expected).toContain('00f3');
    });

    it('sizes the page from the options', () => {
        const text = decode(buildPdf([{ jpeg: fakeJpeg(10), width: 10, height: 10 }], options));

        expect(text).toContain('/MediaBox [0 0 841.89 595.28]');
        expect(text).toContain('q 841.89 0 0 595.28 0 0 cm /Im0 Do Q');
    });

    it('refuses to write a PDF with no pages', () => {
        expect(() => buildPdf([], options)).toThrow('at least one page');
    });
});
