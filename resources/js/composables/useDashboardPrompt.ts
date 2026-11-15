import { showToast } from '@/composables/useToast';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

// Kept in step with StoreDashboardRequest, which is the authority.
export const MIN_PROMPT_LENGTH = 10;
export const MAX_PROMPT_LENGTH = 2000;

/**
 * The one place that turns a sentence into a dashboard, so that Home, "Nueva
 * pregunta" and the command palette behave the same: Enter creates it right there,
 * with no intermediate screen that asks for a second Enter.
 */
export function useDashboardPrompt(initialPrompt = '') {
    const form = useForm({ prompt: initialPrompt });

    const canSubmit = computed(() => !form.processing && form.prompt.trim().length >= MIN_PROMPT_LENGTH);

    const submit = () => {
        if (!canSubmit.value) return;

        form.post(route('dashboards.store'), { preserveScroll: true });
    };

    // Enter sends; Shift+Enter adds a line; an IME composition (accents, CJK) is left alone.
    const handleKeydown = (event: KeyboardEvent) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

        event.preventDefault();
        submit();
    };

    return { form, canSubmit, submit, handleKeydown };
}

/**
 * For callers that have no form of their own (the command palette): creates the
 * dashboard and says so, since the palette closes as soon as it is chosen.
 */
export function createDashboardFrom(prompt: string): void {
    const text = prompt.trim();

    if (text.length < MIN_PROMPT_LENGTH) return;

    router.post(
        route('dashboards.store'),
        { prompt: text },
        {
            onStart: () => showToast('Diseñando tu dashboard…', 8000),
            onError: (errors) => showToast(errors.prompt ?? 'No se pudo crear el dashboard. Inténtalo de nuevo.', 6000),
        },
    );
}
