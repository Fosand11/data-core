import { describe, expect, it } from 'vitest';
import { fitText, planPages, rowHeightFor, spanHeight, type RowSpan } from './sheetLayout';

const card = (y: number, height: number): RowSpan => ({ y, height });

describe('planPages', () => {
    it('keeps a dashboard that fits on a single page', () => {
        expect(planPages([card(0, 2), card(2, 4), card(6, 4)], 12)).toEqual([{ from: 0, to: 10 }]);
    });

    it('splits a tall dashboard at the row where no card is crossed', () => {
        // KPIs (2) + two chart rows (4 + 4) + a table (5): 15 rows on a page that holds 10.
        const cards = [card(0, 2), card(0, 2), card(2, 4), card(2, 4), card(6, 4), card(6, 4), card(10, 5)];

        expect(planPages(cards, 10)).toEqual([
            { from: 0, to: 10 },
            { from: 10, to: 15 },
        ]);
    });

    it('never cuts a card in two: the break moves up to the last free row', () => {
        // Capacity 7 would cut the card spanning rows 6-10, so the first page ends at row 6.
        const pages = planPages([card(0, 2), card(2, 4), card(6, 4), card(10, 2)], 7);

        expect(pages).toEqual([
            { from: 0, to: 6 },
            { from: 6, to: 12 },
        ]);

        for (const page of pages) {
            for (const c of [card(0, 2), card(2, 4), card(6, 4), card(10, 2)]) {
                const inside = c.y >= page.from && c.y + c.height <= page.to;
                const outside = c.y + c.height <= page.from || c.y >= page.to;

                expect(inside || outside).toBe(true);
            }
        }
    });

    it('puts a card taller than a page on a page of its own, whole', () => {
        const pages = planPages([card(0, 2), card(2, 12), card(14, 2)], 6);

        expect(pages).toEqual([
            { from: 0, to: 2 },
            { from: 2, to: 14 },
            { from: 14, to: 16 },
        ]);
    });

    it('skips empty rows instead of leaving a blank band', () => {
        expect(planPages([card(0, 2), card(8, 2)], 3)).toEqual([
            { from: 0, to: 2 },
            { from: 8, to: 10 },
        ]);
    });

    it('handles a dashboard with no visualizations', () => {
        expect(planPages([], 8)).toEqual([{ from: 0, to: 0 }]);
    });

    it('treats a capacity below one as one row so it always terminates', () => {
        expect(planPages([card(0, 1), card(1, 1)], 0)).toEqual([
            { from: 0, to: 1 },
            { from: 1, to: 2 },
        ]);
    });

    it('covers every card exactly once whatever the layout and capacity', () => {
        const cards = [card(0, 2), card(0, 2), card(0, 2), card(2, 4), card(2, 4), card(6, 3), card(6, 3), card(9, 5), card(14, 4)];

        for (let capacity = 1; capacity <= 20; capacity++) {
            const pages = planPages(cards, capacity);
            const placed = cards.map((c) => pages.filter((page) => c.y >= page.from && c.y + c.height <= page.to).length);

            expect(placed, `capacity ${capacity}`).toEqual(cards.map(() => 1));
        }
    });
});

describe('row heights', () => {
    it('adds the gaps between rows but not after the last one', () => {
        expect(spanHeight(1, 72, 16)).toBe(72);
        expect(spanHeight(4, 72, 16)).toBe(4 * 72 + 3 * 16);
    });

    it('never stretches a sparse page past the normal row height', () => {
        expect(rowHeightFor(3, 2000, 16, 72)).toBe(72);
    });

    it('shrinks the rows so a full page fits the space it has', () => {
        const rowPx = rowHeightFor(20, 800, 16, 72);

        expect(spanHeight(20, rowPx, 16)).toBeCloseTo(800);
        expect(rowPx).toBeLessThan(72);
    });

    it('never collapses to nothing', () => {
        expect(rowHeightFor(200, 100, 16, 72)).toBeGreaterThanOrEqual(8);
    });
});

describe('fitText', () => {
    const measure = (text: string) => text.length * 10;

    it('leaves text that fits untouched', () => {
        expect(fitText('Norte', 100, measure)).toBe('Norte');
    });

    it('cuts to the longest prefix that fits with an ellipsis', () => {
        expect(fitText('Ingreso mensual', 80, measure)).toBe('Ingreso…');
        expect(measure(fitText('Ingreso mensual', 80, measure))).toBeLessThanOrEqual(80);
    });

    it('returns nothing when there is no room even for an ellipsis', () => {
        expect(fitText('Norte', 5, measure)).toBe('');
        expect(fitText('Norte', 0, measure)).toBe('');
    });
});
