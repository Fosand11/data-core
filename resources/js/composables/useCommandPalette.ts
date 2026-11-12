import { ref } from 'vue';

// Module-level state so the header button, the keyboard shortcut and the palette share one source of truth.
const open = ref(false);

export function useCommandPalette() {
    return {
        open,
        show: () => {
            open.value = true;
        },
        hide: () => {
            open.value = false;
        },
        toggle: () => {
            open.value = !open.value;
        },
    };
}
