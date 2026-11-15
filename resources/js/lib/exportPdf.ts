import { renderDashboardSheets } from '@/lib/dashboardSheets';
import { A4_LANDSCAPE, buildPdf, type PdfPage } from '@/lib/pdf';
import type { DashboardSpec, ExportContext, VisualizationData } from '@/types';

const toJpeg = (canvas: HTMLCanvasElement): Promise<Uint8Array> =>
    new Promise((resolve, reject) => {
        canvas.toBlob(
            async (blob) => (blob ? resolve(new Uint8Array(await blob.arrayBuffer())) : reject(new Error('The sheet could not be encoded.'))),
            'image/jpeg',
            0.92,
        );
    });

/**
 * The whole dashboard as a PDF file to download (A4 landscape). It does not open the print
 * dialog: the sheets are drawn off-screen, so nothing on the page (an open menu, a toast)
 * can end up in the file, and the viewer's theme never matters.
 */
export async function buildDashboardPdf(spec: DashboardSpec, data: VisualizationData, context: ExportContext): Promise<Blob> {
    const sheets = await renderDashboardSheets(spec, data, context, { aspect: A4_LANDSCAPE.pageWidth / A4_LANDSCAPE.pageHeight });
    const pages: PdfPage[] = [];

    for (const sheet of sheets) pages.push({ jpeg: await toJpeg(sheet), width: sheet.width, height: sheet.height });

    return new Blob([buildPdf(pages, { ...A4_LANDSCAPE, title: context.title }) as BlobPart], { type: 'application/pdf' });
}
