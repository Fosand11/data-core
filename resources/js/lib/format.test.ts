import { describe, expect, it } from 'vitest';
import { IN_PROGRESS_SUFFIX, formatDimension, formatRange, formatRelative, formatValue, isCurrentMonth } from './format';

describe('formatValue', () => {
    it('shows whole amounts without cents and fractional ones with cents', () => {
        expect(formatValue(186305, 'currency')).toBe('$186,305');
        expect(formatValue(824.38, 'currency')).toBe('$824.38');
        expect(formatValue(0, 'currency', { compact: true })).toBe('$0');
    });

    it('only compacts from the thousands and tightens the suffix', () => {
        expect(formatValue(824.38, 'currency', { compact: true })).toBe('$824.38');
        expect(formatValue(175600, 'currency', { compact: true })).toBe('$175.6K');
        expect(formatValue(1434, 'integer', { compact: true })).toBe('1.4K');
    });

    it('handles empty and non numeric values', () => {
        expect(formatValue(null, 'number')).toBe('—');
        expect(formatValue('', 'number')).toBe('—');
        expect(formatValue('abc', 'number')).toBe('abc');
        expect(formatValue('12.5', 'percent')).toBe('12.5%');
    });
});

describe('formatDimension', () => {
    it('labels missing values', () => {
        expect(formatDimension(null, { type: 'string' })).toBe('Sin dato');
    });

    it('formats months and flags the one in progress', () => {
        expect(formatDimension('2026-09', { type: 'month' })).toContain('2026');
        expect(formatDimension('2026-11', { type: 'month' }, '2026-11').endsWith(IN_PROGRESS_SUFFIX)).toBe(true);
        expect(formatDimension('2026-10', { type: 'month' }, '2026-11').endsWith(IN_PROGRESS_SUFFIX)).toBe(false);
    });

    it('only treats month columns as having a month in progress', () => {
        expect(isCurrentMonth('2026-11', { type: 'string' }, '2026-11')).toBe(false);
        expect(isCurrentMonth('2026-11', { type: 'month' }, undefined)).toBe(false);
        expect(isCurrentMonth('2026-11', { type: 'month' }, '2026-11')).toBe(true);
    });
});

describe('formatRange', () => {
    it('repeats the year only when it changes', () => {
        expect(formatRange('2026-07-01', '2026-09-30')).toMatch(/^1 jul – 30 sept?\.? 2026$/);
        expect(formatRange('2025-12-01', '2026-11-30')).toContain('2025');
    });
});

describe('formatRelative', () => {
    const now = new Date('2026-11-12T12:00:00Z');

    it('speaks in minutes, hours and days', () => {
        expect(formatRelative('2026-11-12T11:59:40Z', now)).toBe('justo ahora');
        expect(formatRelative('2026-11-12T11:55:00Z', now)).toBe('hace 5 minutos');
        expect(formatRelative('2026-11-12T09:00:00Z', now)).toBe('hace 3 horas');
        expect(formatRelative('2026-11-11T12:00:00Z', now)).toBe('ayer');
    });

    it('falls back to a date after a month', () => {
        expect(formatRelative('2026-08-01T12:00:00Z', now)).toContain('2026');
    });
});
