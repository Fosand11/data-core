/**
 * A minimal PDF writer: one JPEG image per page, nothing else. The dashboard sheets are
 * drawn as pictures, so a full PDF library would be a heavy dependency for what is only a
 * container. The output is a plain PDF 1.4 file (catalog, page tree, one XObject per page,
 * cross-reference table) that every viewer opens.
 */

export interface PdfPage {
    /** The page picture, already JPEG encoded. */
    jpeg: Uint8Array;
    /** Pixel size of the picture. */
    width: number;
    height: number;
}

export interface PdfOptions {
    title: string;
    /** Page size in points (1/72 inch). */
    pageWidth: number;
    pageHeight: number;
}

/** A4 landscape, in points. */
export const A4_LANDSCAPE = { pageWidth: 841.89, pageHeight: 595.28 } as const;

const encoder = new TextEncoder();
const ascii = (text: string) => encoder.encode(text);

/** A PDF text string in UTF-16BE hex, so accents and any language survive. */
function pdfText(text: string): string {
    let hex = 'FEFF';

    for (let index = 0; index < text.length; index++) hex += text.charCodeAt(index).toString(16).padStart(4, '0');

    return `<${hex}>`;
}

export function buildPdf(pages: PdfPage[], options: PdfOptions): Uint8Array {
    if (pages.length === 0) throw new Error('A PDF needs at least one page.');

    const chunks: Uint8Array[] = [];
    const offsets: number[] = [];
    let length = 0;

    const push = (chunk: Uint8Array) => {
        chunks.push(chunk);
        length += chunk.length;
    };
    const object = (number: number, body: Uint8Array | string) => {
        offsets[number] = length;
        push(ascii(`${number} 0 obj\n`));
        push(typeof body === 'string' ? ascii(body) : body);
        push(ascii('\nendobj\n'));
    };

    // Object numbers: 1 catalog, 2 page tree, 3 info, then three per page (page, content, image).
    const pageObject = (index: number) => 4 + index * 3;
    const number = (value: number) => value.toFixed(2).replace(/\.?0+$/, '');

    push(ascii('%PDF-1.4\n'));
    // A comment with high-bit bytes tells transfer tools this file is binary.
    push(new Uint8Array([0x25, 0xe2, 0xe3, 0xcf, 0xd3, 0x0a]));

    object(1, '<< /Type /Catalog /Pages 2 0 R >>');
    object(2, `<< /Type /Pages /Count ${pages.length} /Kids [${pages.map((_, index) => `${pageObject(index)} 0 R`).join(' ')}] >>`);
    object(3, `<< /Title ${pdfText(options.title)} /Producer ${pdfText('Atisbo')} >>`);

    pages.forEach((page, index) => {
        const pageNumber = pageObject(index);
        const content = `q ${number(options.pageWidth)} 0 0 ${number(options.pageHeight)} 0 0 cm /Im0 Do Q`;

        object(
            pageNumber,
            `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${number(options.pageWidth)} ${number(options.pageHeight)}] ` +
                `/Resources << /XObject << /Im0 ${pageNumber + 2} 0 R >> >> /Contents ${pageNumber + 1} 0 R >>`,
        );
        object(pageNumber + 1, `<< /Length ${content.length} >>\nstream\n${content}\nendstream`);

        const header = ascii(
            `<< /Type /XObject /Subtype /Image /Width ${page.width} /Height ${page.height} ` +
                `/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${page.jpeg.length} >>\nstream\n`,
        );
        const footer = ascii('\nendstream');
        const body = new Uint8Array(header.length + page.jpeg.length + footer.length);

        body.set(header, 0);
        body.set(page.jpeg, header.length);
        body.set(footer, header.length + page.jpeg.length);
        object(pageNumber + 2, body);
    });

    const total = 4 + pages.length * 3;
    const xrefAt = length;
    let xref = `xref\n0 ${total}\n0000000000 65535 f \n`;

    for (let index = 1; index < total; index++) xref += `${String(offsets[index]).padStart(10, '0')} 00000 n \n`;

    xref += `trailer\n<< /Size ${total} /Root 1 0 R /Info 3 0 R >>\nstartxref\n${xrefAt}\n%%EOF\n`;
    push(ascii(xref));

    const file = new Uint8Array(length);
    let cursor = 0;

    for (const chunk of chunks) {
        file.set(chunk, cursor);
        cursor += chunk.length;
    }

    return file;
}
