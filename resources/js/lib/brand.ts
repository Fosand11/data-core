/** The studio behind the project. */
export const STUDIO = {
    name: 'Cifra Studios',
    url: 'https://cifra-studios-core.vercel.app/',
} as const;

/** The name shown when the page does not say otherwise (the page's `application-name` meta tag normally does). */
const FALLBACK_NAME = 'Atisbo';

/**
 * The product name, read at runtime from the `application-name` meta tag the server renders
 * from `config('app.name')`. It must not come from a build-time variable: the production image
 * is built without an .env file, so such a value would silently fall back to a default.
 */
export function appName(): string {
    if (typeof document === 'undefined') return FALLBACK_NAME;

    return document.querySelector('meta[name="application-name"]')?.getAttribute('content')?.trim() || FALLBACK_NAME;
}
