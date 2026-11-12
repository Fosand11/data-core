import { onBeforeUnmount, onMounted, ref } from 'vue';

export interface ChartTheme {
    /** Categorical slots in their validated, fixed order. */
    series: string[];
    surface: string;
    text: string;
    muted: string;
    grid: string;
}

const SERIES_TOKENS = ['--viz-1', '--viz-2', '--viz-3', '--viz-4', '--viz-5', '--viz-6'];

/**
 * The light palette, mirroring the tokens in app.css. Exports and print always
 * use it so a file looks the same whatever theme the author was viewing, and it
 * is the initial value before the DOM tokens are read.
 */
export const LIGHT_CHART_THEME: ChartTheme = {
    series: ['#0E8F63', '#E0A31B', '#3B7DD8', '#E5634B', '#8A6FD6', '#14A3B8'],
    surface: '#ffffff',
    text: '#14211C',
    muted: '#5A6962',
    grid: '#DDE3DE',
};

const FALLBACK = LIGHT_CHART_THEME;

/** `221 39% 11%` (shadcn style triplet) -> `hsl(221, 39%, 11%)`, which canvas color parsers accept. */
const hsl = (triplet: string, fallback: string): string => (triplet ? `hsl(${triplet.trim().split(/\s+/).join(', ')})` : fallback);

function readTheme(): ChartTheme {
    const styles = getComputedStyle(document.documentElement);
    const token = (name: string) => styles.getPropertyValue(name).trim();

    return {
        series: SERIES_TOKENS.map((name, index) => token(name) || FALLBACK.series[index]),
        surface: hsl(token('--card'), FALLBACK.surface),
        text: hsl(token('--foreground'), FALLBACK.text),
        muted: hsl(token('--muted-foreground'), FALLBACK.muted),
        grid: hsl(token('--border'), FALLBACK.grid),
    };
}

/** Chart colors read from the design tokens; re-read whenever the `dark` class toggles. */
export function useChartTheme() {
    const theme = ref<ChartTheme>(FALLBACK);
    let observer: MutationObserver | undefined;

    onMounted(() => {
        theme.value = readTheme();
        observer = new MutationObserver(() => {
            theme.value = readTheme();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });

    onBeforeUnmount(() => observer?.disconnect());

    return theme;
}
