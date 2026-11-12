import { ref } from 'vue';

// Module-level so server flash messages and client-side notices share one toast.
const message = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

export function showToast(text: string, duration = 4500): void {
    message.value = text;
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), duration);
}

export function useToast() {
    return { message };
}
