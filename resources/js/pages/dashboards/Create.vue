<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import AppLabel from '@/components/AppLabel.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, TenantOption } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Database, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    initialPrompt: string;
    provider: string;
}>();

const form = useForm({
    prompt: props.initialPrompt,
});

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
    form.post(route('dashboards.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Crear dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <header>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-h2">Crear dashboard con IA</h1>
                    <StatusBadge variant="primary">Proveedor {{ provider }}</StatusBadge>
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                    Describe el análisis que quieres preparar. InsightFlow generará una definición estructurada y segura para revisión técnica.
                </p>
            </header>

            <div class="mt-8 rounded-xl border border-info/20 bg-blue-50 p-4 text-sm text-blue-900" role="note">
                <strong>Generador de demostración.</strong> Aún no se está consultando una base de datos. No se realizarán llamadas a OpenAI ni
                Metabase.
            </div>

            <form class="mt-6 rounded-2xl border bg-card p-5 shadow-card sm:p-7" @submit.prevent="submit">
                <div>
                    <AppLabel for="prompt">¿Qué quieres analizar?</AppLabel>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="7"
                        maxlength="2000"
                        required
                        autofocus
                        :aria-invalid="Boolean(form.errors.prompt)"
                        :aria-describedby="form.errors.prompt ? 'prompt-error prompt-counter' : 'prompt-counter'"
                        class="mt-2 w-full resize-y rounded-xl border border-input bg-background px-4 py-3 text-base text-foreground shadow-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
                        placeholder="Ej.: Muéstrame las ventas por región del último trimestre."
                    />
                    <div class="mt-2 flex items-start justify-between gap-4">
                        <InputError id="prompt-error" :message="form.errors.prompt" />
                        <p id="prompt-counter" class="ml-auto shrink-0 text-xs text-muted-foreground" aria-live="polite">
                            {{ form.prompt.length }} / 2000
                        </p>
                    </div>
                </div>

                <fieldset class="mt-7">
                    <legend class="text-sm font-medium text-foreground">Sugerencias rápidas</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            class="min-h-14 rounded-xl border bg-background px-4 py-3 text-left text-sm text-foreground transition-colors hover:border-primary/40 hover:bg-primary-subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="form.prompt = suggestion"
                        >
                            <Sparkles class="mb-2 size-4 text-primary" aria-hidden="true" />
                            {{ suggestion }}
                        </button>
                    </div>
                </fieldset>

                <div class="mt-7">
                    <AppLabel for="data-source">Fuente de datos</AppLabel>
                    <button
                        id="data-source"
                        type="button"
                        class="mt-2 flex min-h-14 w-full cursor-not-allowed items-center gap-3 rounded-xl border bg-muted/40 px-4 text-left text-sm text-muted-foreground"
                        disabled
                    >
                        <Database class="size-5" aria-hidden="true" />
                        <span>
                            <strong class="block font-medium text-foreground">Dataset de demostración: demo_sales</strong>
                            Las conexiones de fuentes estarán disponibles en una fase posterior.
                        </span>
                    </button>
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-muted-foreground">
                        Se guardará el prompt original y el DashboardSpec validado; nunca razonamientos internos.
                    </p>
                    <AppButton type="submit" class="w-full shrink-0 sm:w-auto" :loading="form.processing" :disabled="form.prompt.trim().length < 10">
                        <Sparkles aria-hidden="true" />
                        {{ form.processing ? 'Creando…' : 'Generar dashboard' }}
                    </AppButton>
                </div>
            </form>
        </main>
    </AppLayout>
</template>
