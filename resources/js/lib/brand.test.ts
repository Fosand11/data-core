import { afterEach, describe, expect, it, vi } from 'vitest';
import { appName } from './brand';

const withMeta = (content: string | null) =>
    vi.stubGlobal('document', {
        querySelector: (selector: string) =>
            selector === 'meta[name="application-name"]' && content !== null
                ? { getAttribute: (name: string) => (name === 'content' ? content : null) }
                : null,
    });

describe('appName', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('reads the name the server rendered into the page', () => {
        withMeta('Otro nombre');

        expect(appName()).toBe('Otro nombre');
    });

    it('trims it and falls back to the product name when the tag is missing or empty', () => {
        withMeta('  Atisbo  ');
        expect(appName()).toBe('Atisbo');

        withMeta('   ');
        expect(appName()).toBe('Atisbo');

        withMeta(null);
        expect(appName()).toBe('Atisbo');
    });

    it('never returns the framework default', () => {
        withMeta(null);

        expect(appName()).not.toBe('Laravel');
    });

    it('works without a document at all (server-side rendering, tests)', () => {
        expect(appName()).toBe('Atisbo');
    });
});
