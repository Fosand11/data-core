<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import AppLabel from '@/components/AppLabel.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, TenantOption } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CornerDownLeft, Database, Sparkles } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    initialPrompt: string;
    provider: string;
    providerModel: string | null;
}>();

const form = useForm({
    prompt: props.initialPrompt,
});
const promptInput = ref<HTMLTextAreaElement | null>(null);

const suggestions = [
    'Muéstrame las ventas por región del último trimestre.',
    'Comparar ingresos con el periodo anterior.',
    'Identifica productos con ventas disminuidas.',
    'Resume el rendimiento del equipo comercial.',
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboards', href: '/dashboards' },
    { title: 'Crear con IA', href: '/dashboards/create' },
];

const submit = () => {
    if (form.processing || form.prompt.trim().length < 10) return;

    form.post(route('dashboards.store'), {
        preserveScroll: true,
    });
};

const resizePrompt = async () => {
    await nextTick();

    const input = promptInput.value;
    if (!input) return;

    input.style.height = 'auto';
    const nextHeight = Math.min(Math.max(input.scrollHeight, 88), 240);
    input.style.height = `${nextHeight}px`;
    input.style.overflowY = input.scrollHeight > 240 ? 'auto' : 'hidden';
};

const selectSuggestion = (suggestion: string) => {
    form.prompt = suggestion;
    promptInput.value?.focus();
};

const handlePromptKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

    event.preventDefault();
    submit();
};

watch(() => form.prompt, resizePrompt);
onMounted(resizePrompt);
</script>

<template>
    <Head title="Crear dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <header class="motion-reveal relative overflow-hidden rounded-[1.5rem] border bg-card px-5 py-6 shadow-card sm:px-7">
                <div
                    class="pointer-events-none absolute -right-12 -top-16 size-52 rounded-full bg-[radial-gradient(circle,_hsl(var(--primary)/0.16)_1px,_transparent_1.5px)] bg-[length:14px_14px]"
                    aria-hidden="true"
                />
                <div class="relative">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                        <span class="size-2 rounded-full bg-primary shadow-[0_0_0_5px_hsl(var(--primary)/0.1)]" aria-hidden="true" />
                        Nueva conversación analítica
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-h2">Convierte una pregunta en estructura</h1>
                        <StatusBadge variant="primary">
                            {{ provider === 'openai' ? 'Generador de IA' : 'Simulador local' }}
                        </StatusBadge>
                    </div>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Empieza con lenguaje natural. InsightFlow organizará tu intención en métricas, dimensiones y visualizaciones listas para
                        revisar.
                    </p>
                </div>
            </header>

            <div
                class="motion-reveal-delayed mt-5 flex items-start gap-3 rounded-xl border border-info/20 bg-info/10 px-4 py-3 text-sm text-foreground"
                role="note"
            >
                <Sparkles class="mt-0.5 size-4 shrink-0 text-info" aria-hidden="true" />
                <p>
                    <template v-if="provider === 'openai'">
                        <strong>Generación real de estructura.</strong> Aún no se están consultando datos reales. La IA está diseñando la estructura
                        del dashboard con tu solicitud y el catálogo permitido de <code>demo_sales</code>; no recibe filas ni ejecuta consultas.
                    </template>
                    <template v-else>
                        <strong>Estudio de demostración.</strong> El simulador local diseña la definición con <code>demo_sales</code>; no se consulta
                        una base de datos ni se llama a OpenAI o Metabase.
                    </template>
                </p>
            </div>

            <form
                class="motion-reveal-late relative mt-5 overflow-hidden rounded-[1.75rem] border border-primary/20 bg-card shadow-soft"
                @submit.prevent="submit"
            >
                <div class="absolute left-0 top-14 h-24 w-1 rounded-r-full bg-primary" aria-hidden="true" />
                <div class="flex items-center justify-between gap-4 border-b bg-primary-subtle/40 px-5 py-3 sm:px-7">
                    <div class="flex items-center gap-2 text-xs font-semibold text-primary">
                        <span class="flex gap-1" aria-hidden="true">
                            <span class="size-1.5 rounded-full bg-primary/30" />
                            <span class="size-1.5 rounded-full bg-primary/60" />
                            <span class="size-1.5 rounded-full bg-primary" />
                        </span>
                        Lienzo de consulta
                    </div>
                    <span class="text-xs text-muted-foreground">{{ currentTenant.name }}</span>
                </div>

                <div class="px-5 pb-4 pt-5 sm:px-7">
                    <AppLabel for="prompt" class="text-base font-semibold">¿Qué necesitas comprender?</AppLabel>
                    <textarea
                        id="prompt"
                        ref="promptInput"
                        v-model="form.prompt"
                        rows="2"
                        maxlength="2000"
                        required
                        autofocus
                        :aria-invalid="Boolean(form.errors.prompt)"
                        :aria-describedby="form.errors.prompt ? 'prompt-error prompt-help prompt-counter' : 'prompt-help prompt-counter'"
                        class="mt-2 block min-h-[5.5rem] w-full resize-none border-0 bg-transparent p-0 text-base leading-7 text-foreground outline-none placeholder:text-muted-foreground focus:ring-0 sm:text-lg"
                        placeholder="Ej.: Quiero entender cómo cambiaron las ventas por región durante el último trimestre…"
                        @keydown="handlePromptKeydown"
                    />
                    <div class="mt-2 flex min-h-5 items-start justify-between gap-4">
                        <InputError id="prompt-error" :message="form.errors.prompt" />
                        <p id="prompt-counter" class="ml-auto shrink-0 text-xs text-muted-foreground" aria-live="polite">
                            {{ form.prompt.length }} / 2000
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t bg-muted/20 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-7 sm:py-4">
                    <AppButton
                        type="submit"
                        class="w-full min-w-44 shrink-0 sm:order-2 sm:w-auto"
                        :loading="form.processing"
                        :disabled="form.prompt.trim().length < 10"
                    >
                        <Sparkles aria-hidden="true" />
                        {{ form.processing ? 'Dando forma…' : 'Generar dashboard' }}
                    </AppButton>
                    <p id="prompt-help" class="inline-flex items-center gap-2 text-xs text-muted-foreground sm:order-1">
                        <CornerDownLeft class="size-3.5" aria-hidden="true" />
                        Enter para generar · Shift + Enter para nueva línea
                    </p>
                </div>
            </form>

            <div class="mt-7 grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.6fr)]">
                <fieldset class="rounded-2xl border bg-card p-5 shadow-card sm:p-6">
                    <legend class="px-2 text-sm font-semibold text-foreground">Puntos de partida</legend>
                    <p class="mb-4 text-sm text-muted-foreground">No son plantillas rígidas: elige una y hazla tuya.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            class="group relative min-h-20 overflow-hidden rounded-xl border bg-background px-4 py-3 text-left text-sm text-foreground transition-colors before:absolute before:inset-y-3 before:left-0 before:w-0.5 before:rounded-r before:bg-primary before:opacity-0 before:transition-opacity hover:border-primary/40 hover:bg-primary-subtle/50 hover:before:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="selectSuggestion(suggestion)"
                        >
                            <Sparkles class="mb-2 size-4 text-primary transition-transform group-hover:-rotate-12" aria-hidden="true" />
                            {{ suggestion }}
                        </button>
                    </div>
                </fieldset>

                <section class="rounded-2xl border bg-card p-5 shadow-card sm:p-6" aria-labelledby="data-source-title">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-primary-subtle text-primary">
                            <Database class="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 id="data-source-title" class="text-sm font-semibold text-foreground">Contexto de datos</h2>
                            <p class="text-xs text-muted-foreground">Sólo una referencia estructural</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-5 flex min-h-20 w-full cursor-not-allowed flex-col justify-center rounded-xl border border-dashed bg-muted/30 px-4 text-left text-sm text-muted-foreground"
                        disabled
                    >
                        <strong class="font-mono text-xs font-semibold text-foreground">demo_sales</strong>
                        <span class="mt-1 leading-5">Las conexiones reales llegarán en una fase posterior.</span>
                    </button>
                    <p class="mt-4 text-xs leading-5 text-muted-foreground">Guardamos el prompt y el spec validado, nunca razonamientos internos.</p>
                    <p v-if="provider === 'openai' && providerModel" class="mt-2 font-mono text-[0.7rem] text-muted-foreground">
                        Modelo: {{ providerModel }}
                    </p>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
