import { nextTick } from 'vue';

type Preparer = () => Promise<void>;

const preparers = new Set<Preparer>();

/**
 * A component that draws on a canvas registers here so it can hand the browser
 * a static, light-themed picture right before printing. Returns the unregister function.
 */
export function registerPrintPreparer(preparer: Preparer): () => void {
    preparers.add(preparer);

    return () => preparers.delete(preparer);
}

/**
 * Opens the browser print dialog (which can save as PDF) with every chart
 * replaced by a sharp static image. The light theme on paper is handled in CSS
 * (see the print rules in app.css), so nothing has to be restored afterwards.
 */
export async function printDashboard(): Promise<void> {
    await Promise.all([...preparers].map((prepare) => prepare()));
    await nextTick();
    // Let the browser lay out and decode the images before the dialog freezes the page.
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    window.print();
}
