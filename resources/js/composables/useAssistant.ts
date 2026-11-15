import type { AssistantReply, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * The assistant's latest answer, as the server flashed it. Dismissing hides it for this
 * visit; a new answer shows again.
 */
export function useAssistantReply() {
    const page = usePage<SharedData>();
    const dismissed = ref(false);
    const incoming = computed<AssistantReply | null>(() => page.props.flash?.assistant ?? null);

    watch(incoming, () => (dismissed.value = false));

    return {
        reply: computed(() => (dismissed.value ? null : incoming.value)),
        dismiss: () => (dismissed.value = true),
    };
}

// Only the flash is refreshed, so what the user was typing and where they were scrolled stay as they are.
const keep: NonNullable<Parameters<typeof router.post>[2]> = { preserveScroll: true, preserveState: true, only: ['flash'] };

/** Asks about the app on a screen that creates a dashboard. The server answers instead of generating one. */
export function askAssistant(question: string, datasetId: string | null): void {
    router.post(route('dashboards.store'), { prompt: question, dataset_id: datasetId }, keep);
}

/** Asks what can be changed in an existing dashboard. */
export function askAssistantAbout(dashboardId: string, question: string): void {
    router.post(route('dashboards.refinements.store', { dashboard: dashboardId }), { instruction: question }, keep);
}
