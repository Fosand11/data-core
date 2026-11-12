<script setup lang="ts">
import DashboardSpecPreview from '@/components/DashboardSpecPreview.vue';
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, DashboardDetail, TenantOption } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Clock3, Code2, Sparkles, TriangleAlert } from 'lucide-vue-next';
import { computed, onUnmounted, watch } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    dashboard: DashboardDetail;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboards', href: '/dashboards' },
    { title: props.dashboard.title, href: `/dashboards/${props.dashboard.id}` },
]);

const createdAt = computed(() =>
    new Intl.DateTimeFormat('es', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(props.dashboard.created_at)),
);

const technicalSpec = computed(() => JSON.stringify(props.dashboard.dashboard_spec, null, 2));
const isProcessing = computed(() => props.dashboard.status === 'queued' || props.dashboard.status === 'generating');
let pollingTimer: ReturnType<typeof setInterval> | undefined;

const stopPolling = () => {
    if (pollingTimer !== undefined) {
        clearInterval(pollingTimer);
        pollingTimer = undefined;
    }
};

watch(
    () => props.dashboard.status,
    () => {
        stopPolling();

        if (isProcessing.value) {
            pollingTimer = setInterval(() => {
                router.reload({ only: ['dashboard'] });
            }, 2500);
        }
    },
    { immediate: true },
);

onUnmounted(stopPolling);
</script>

<template>
    <Head :title="dashboard.title" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-[1480px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <Link
                href="/dashboards"
                class="motion-reveal inline-flex min-h-11 items-center gap-2 rounded-full border bg-card px-4 text-sm font-medium text-muted-foreground shadow-card transition-all duration-300 hover:-translate-x-0.5 hover:border-primary/20 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Volver a dashboards
            </Link>

            <header class="motion-reveal-delayed mt-7 border-b border-border/70 pb-8">
                <div class="min-w-0">
                    <p class="editorial-kicker">Pieza analítica</p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <h1 class="max-w-5xl text-3xl font-bold tracking-[-0.045em] text-foreground sm:text-5xl">{{ dashboard.title }}</h1>
                        <DashboardStatusBadge :status="dashboard.status" />
                    </div>
                    <p v-if="dashboard.description" class="mt-4 max-w-3xl text-sm leading-6 text-muted-foreground">{{ dashboard.description }}</p>
                    <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5"><Clock3 class="size-4" aria-hidden="true" />{{ createdAt }}</span>
                        <span v-if="dashboard.author">Creado por {{ dashboard.author.name }}</span>
                        <span v-if="dashboard.provider">provider: {{ dashboard.provider }}</span>
                        <span v-if="dashboard.generation?.model">Modelo: {{ dashboard.generation.model }}</span>
                        <span v-if="dashboard.spec_version">Spec: {{ dashboard.spec_version }}</span>
                    </div>
                </div>
            </header>

            <section
                class="motion-reveal-late insight-panel mt-7 grid gap-5 p-5 sm:grid-cols-[auto_1fr] sm:p-7"
                aria-labelledby="original-prompt-title"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-primary text-white shadow-[0_8px_20px_-10px_hsl(var(--primary))]"
                >
                    <Sparkles class="size-5" aria-hidden="true" />
                </div>
                <div>
                    <h2 id="original-prompt-title" class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-primary">
                        La pregunta que inició todo
                    </h2>
                    <blockquote class="mt-3 whitespace-pre-wrap text-lg font-medium leading-7 tracking-tight text-foreground sm:text-xl">
                        “{{ dashboard.original_prompt || 'No disponible para este dashboard heredado.' }}”
                    </blockquote>
                </div>
            </section>

            <section
                v-if="isProcessing"
                class="mt-7 overflow-hidden rounded-[1.5rem] border bg-card p-6 shadow-card"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-primary-subtle text-primary">
                        <Sparkles class="motion-soft-pulse size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="font-semibold text-foreground">
                            {{ dashboard.status === 'queued' ? 'Generación en cola' : 'Construyendo DashboardSpec' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">Esta página se actualizará automáticamente hasta finalizar.</p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <Skeleton class="h-44 rounded-xl" />
                    <Skeleton class="h-44 rounded-xl" />
                </div>
            </section>

            <section v-else-if="dashboard.status === 'failed'" class="mt-7 rounded-xl border border-error/20 bg-error/10 p-5" role="alert">
                <div class="flex items-start gap-3">
                    <TriangleAlert class="mt-0.5 size-5 shrink-0 text-error" aria-hidden="true" />
                    <div>
                        <h2 class="font-semibold text-foreground">No se pudo generar el dashboard</h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            {{ dashboard.error_message || 'La generación terminó con un error seguro.' }}
                        </p>
                    </div>
                </div>
            </section>

            <div v-else-if="dashboard.status === 'archived'" class="mt-7 rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Este dashboard proviene de una fase anterior y no contiene un DashboardSpec.</p>
            </div>

            <div v-else-if="dashboard.dashboard_spec" class="mt-8">
                <DashboardSpecPreview :spec="dashboard.dashboard_spec" />

                <details class="mt-8 overflow-hidden rounded-[1.25rem] border bg-card shadow-card">
                    <summary
                        class="flex min-h-14 cursor-pointer list-none items-center gap-2 px-5 font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                    >
                        <Code2 class="size-4 text-primary" aria-hidden="true" />
                        DashboardSpec técnico
                        <StatusBadge variant="primary" class="ml-auto">JSON validado</StatusBadge>
                    </summary>
                    <pre
                        class="max-h-[32rem] overflow-auto border-t bg-slate-950 p-5 text-xs leading-5 text-slate-100"
                    ><code>{{ technicalSpec }}</code></pre>
                </details>
            </div>
        </main>
    </AppLayout>
</template>
