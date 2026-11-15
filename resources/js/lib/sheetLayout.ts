/**
 * Layout maths for the "whole dashboard" sheets used by the PDF and the PowerPoint overview.
 * Pure on purpose: it has no canvas and no DOM, so it can be tested on its own.
 */

export interface RowSpan {
    /** First grid row of the card (0-based). */
    y: number;
    /** How many grid rows it covers. */
    height: number;
}

export interface PagePlan {
    /** First grid row on the page. */
    from: number;
    /** Row after the last one on the page. */
    to: number;
}

/**
 * Splits a dashboard into pages without ever cutting a card in two. A page break may only
 * fall on a row that no card crosses; the page takes as many rows as fit, and a card taller
 * than a whole page is kept together (the caller shrinks the rows to make it fit).
 */
export function planPages(cards: RowSpan[], capacityRows: number): PagePlan[] {
    if (cards.length === 0) return [{ from: 0, to: 0 }];

    const capacity = Math.max(1, Math.floor(capacityRows));
    const end = Math.max(...cards.map((card) => card.y + card.height));
    const crosses = (row: number) => cards.some((card) => card.y < row && row < card.y + card.height);
    const pages: PagePlan[] = [];
    let from = Math.min(...cards.map((card) => card.y));

    while (from < end) {
        let to: number;

        if (end - from <= capacity) {
            to = end;
        } else {
            to = from + capacity;

            while (to > from && crosses(to)) to--;

            // Nothing fits cleanly: a card is taller than the page, so it goes alone, whole.
            if (to <= from) {
                to = from + 1;

                while (to < end && crosses(to)) to++;
            }
        }

        // End the page at the last row a card actually uses, not at the capacity.
        const onPage = cards.filter((card) => card.y >= from && card.y < to);
        to = Math.max(...onPage.map((card) => card.y + card.height));

        pages.push({ from, to });

        // Skip rows nobody uses instead of leaving a blank band at the top of the next page.
        const next = cards.filter((card) => card.y >= to).map((card) => card.y);
        from = next.length ? Math.min(...next) : end;
    }

    return pages;
}

/** The height in pixels of `rows` grid rows when each is `rowPx` tall and rows are `gap` apart. */
export const spanHeight = (rows: number, rowPx: number, gap: number): number => rows * rowPx + Math.max(0, rows - 1) * gap;

/**
 * The row height that makes `rows` rows fit in `available` pixels, never taller than `max`
 * (a sparse page keeps its normal proportions instead of stretching its cards).
 */
export function rowHeightFor(rows: number, available: number, gap: number, max: number): number {
    if (rows <= 0) return max;

    return Math.max(8, Math.min(max, (available - (rows - 1) * gap) / rows));
}

/**
 * Cuts `text` so it fits `maxWidth`, ending in an ellipsis. `measure` is the canvas'
 * measureText, passed in so this stays testable.
 */
export function fitText(text: string, maxWidth: number, measure: (candidate: string) => number): string {
    if (maxWidth <= 0) return '';
    if (measure(text) <= maxWidth) return text;

    const ellipsis = '…';
    let low = 0;
    let high = text.length;

    // The longest prefix that still fits together with the ellipsis.
    while (low < high) {
        const middle = Math.ceil((low + high) / 2);

        if (measure(text.slice(0, middle).trimEnd() + ellipsis) <= maxWidth) low = middle;
        else high = middle - 1;
    }

    return low === 0 ? '' : text.slice(0, low).trimEnd() + ellipsis;
}
