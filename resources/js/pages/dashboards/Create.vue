<script setup lang="ts">
import AssistantReply from '@/components/AssistantReply.vue';
import InputError from '@/components/InputError.vue';
import { askAssistant, useAssistantReply } from '@/composables/useAssistant';
import { useDashboardPrompt } from '@/composables/useDashboardPrompt';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, DatasetChoice, TenantOption } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowUp, CircleHelp, Database, LoaderCircle, ShieldCheck } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    initialPrompt: string;
    datasets: DatasetChoice[];
    datasetId: string | null;
    provider: string;
    providerModel: string | null;
}>();

const { form, canSubmit, submit, handleKeydown: handlePromptKeydown } = useDashboardPrompt(props.initialPrompt, props.datasetId);
const promptInput = ref<HTMLTextAreaElement | null>(null);

const providerNames: Record<string, string> = { gemini: 'Gemini', openai: 'OpenAI', fake: 'Simulador local' };
const providerLabel = computed(() => providerNames[props.provider] ?? props.provider);
const isExternalProvider = computed(() => props.provider === 'gemini' || props.provider === 'openai');
const demoSuggestions = [
    'Muéstrame las ventas por región del último trimestre.',
    'Comparar ingresos con el periodo anterior.',
    'Identifica productos con ventas disminuidas.',
    'Resume el rendimiento del equipo comercial.',
];

// What the user is asking about: an uploaded dataset, or the demo data when none.
const selectedDataset = computed(() => props.datasets.find((dataset) => dataset.id === form.dataset_id) ?? null);
// The ideas follow the data: an uploaded file offers questions built from its own columns.
const suggestions = computed(() => selectedDataset.value?.suggestions ?? demoSuggestions);

const { reply, dismiss } = useAssistantReply();
const askForHelp = () => askAssistant('¿Qué puedo hacer?', form.dataset_id);

const fields = ['Monto', 'Región', 'Fecha', 'Producto', 'Cantidad', 'Vendedor', 'Equipo'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboards', href: '/dashboards' },
    { title: 'Nueva pregunta', href: '/dashboards/create' },
];

const resizePrompt = async () => {
    await nextTick();

    const input = promptInput.value;
    if (!input) return;

    input.style.height = 'auto';
    input.style.height = `${Math.min(Math.max(input.scrollHeight, 120), 288)}px`;
    input.style.overflowY = input.scrollHeight > 288 ? 'auto' : 'hidden';
};

const selectSuggestion = (suggestion: string) => {
    form.prompt = suggestion;
    promptInput.value?.focus();
};

watch(() => form.prompt, resizePrompt);
onMounted(() => {
    resizePrompt();
    promptInput.value?.focus();
});
</script>

<template>
    <Head title="Nueva pregunta" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-5xl px-4 pb-20 pt-10 sm:px-6 sm:pt-14">
            <header class="motion-reveal">
                <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Nueva pregunta</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-muted-foreground">
                    Cuéntanos qué quieres entender. Diseñamos el dashboard y lo calculamos con tus datos.
                </p>
            </header>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem] lg:items-start">
                <section class="motion-reveal-delayed" aria-label="Describe tu dashboard">
                    <div
                        v-if="datasets.length"
                        class="mb-3 flex flex-wrap items-center gap-2"
                        role="radiogroup"
                        aria-label="Datos sobre los que preguntar"
                    >
                        <span class="px-1 text-sm text-muted-foreground">Sobre:</span>
                        <button
                            v-for="option in [{ id: null, name: 'Datos de ejemplo' }, ...datasets]"
                            :key="option.id ?? 'demo'"
                            type="button"
                            role="radio"
                            :aria-checked="form.dataset_id === option.id"
                            :class="[
                                'rounded-full border px-3.5 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                form.dataset_id === option.id
                                    ? 'border-primary bg-primary-subtle font-medium text-primary'
                                    : 'bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground',
                            ]"
                            @click="form.dataset_id = option.id"
                        >
                            {{ option.name }}
                        </button>
                    </div>
                    <form class="insight-panel p-2 focus-within:border-primary/40" @submit.prevent="submit">
                        <label for="prompt" class="sr-only">Describe el dashboard que necesitas</label>
                        <textarea
                            id="prompt"
                            ref="promptInput"
                            v-model="form.prompt"
                            rows="4"
                            maxlength="2000"
                            class="min-h-[7.5rem] w-full resize-none border-0 bg-transparent px-4 pb-2 pt-4 text-lg leading-7 tracking-tight text-foreground outline-none placeholder:text-muted-foreground/70 focus:ring-0"
                            placeholder="Por ejemplo: ventas por región del último trimestre, con la evolución mes a mes"
                            :aria-invalid="form.errors.prompt ? 'true' : undefined"
                            @keydown="handlePromptKeydown"
                        />
                        <div class="flex items-center justify-between gap-3 px-3 pb-2 pt-1">
                            <span class="min-w-0 truncate text-xs text-muted-foreground">
                                {{ providerLabel }}<template v-if="isExternalProvider && providerModel"> · {{ providerModel }}</template>
                            </span>
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-all hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground"
                                aria-label="Crear dashboard"
                            >
                                <LoaderCircle v-if="form.processing" class="size-5 animate-spin" aria-hidden="true" />
                                <ArrowUp v-else class="size-5" aria-hidden="true" />
                            </button>
                        </div>
                    </form>
                    <InputError class="mt-2 px-2" :message="form.errors.prompt" />
                    <p v-if="form.processing" class="mt-3 px-2 text-sm text-muted-foreground" role="status">
                        Diseñando tu dashboard… puede tardar unos segundos.
                    </p>

                    <div class="mt-6 flex items-center justify-between gap-3 px-1">
                        <p class="text-sm font-medium text-foreground">Ideas para empezar</p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full text-sm text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="askForHelp"
                        >
                            <CircleHelp class="size-4" aria-hidden="true" /> ¿Qué puedo hacer?
                        </button>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            class="rounded-xl border bg-card px-4 py-3 text-left text-sm leading-5 text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="selectSuggestion(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>

                    <AssistantReply v-if="reply" :reply="reply" @use="selectSuggestion" @close="dismiss" />
                </section>

                <aside class="motion-reveal-late space-y-4" aria-label="Datos disponibles">
                    <div class="rounded-2xl border bg-card p-5">
                        <div class="flex items-center gap-2 text-sm font-medium text-foreground">
                            <Database class="size-4 text-primary" aria-hidden="true" />
                            {{ selectedDataset?.name ?? 'Datos de ejemplo' }}
                        </div>
                        <p v-if="selectedDataset" class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ selectedDataset.row_count.toLocaleString('es') }} filas de tus datos. Pregunta con los nombres de tus columnas.
                        </p>
                        <p v-else class="mt-2 text-sm leading-6 text-muted-foreground">
                            Ventas de los últimos 12 meses de tu organización. Puedes preguntar por:
                        </p>
                        <ul v-if="!selectedDataset" class="mt-3 flex flex-wrap gap-1.5">
                            <li v-for="field in fields" :key="field" class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">
                                {{ field }}
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border bg-card p-5">
                        <div class="flex items-center gap-2 text-sm font-medium text-foreground">
                            <ShieldCheck class="size-4 text-primary" aria-hidden="true" />
                            Tus datos se quedan aquí
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            La IA solo recibe tu pregunta y los nombres de los campos, nunca las filas. Los números se calculan en tu servidor.
                        </p>
                        <p v-if="provider === 'gemini'" class="mt-3 text-xs leading-5 text-muted-foreground">
                            Con el plan gratuito de Gemini, Google puede usar lo que escribas para mejorar sus productos: evita datos personales o
                            confidenciales.
                        </p>
                    </div>
                </aside>
            </div>
        </main>
    </AppLayout>
</template>
