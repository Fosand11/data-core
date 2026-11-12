<script setup lang="ts">
import DashboardCanvas from '@/components/dashboard/DashboardCanvas.vue';
import DashboardActions from '@/components/DashboardActions.vue';
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import ExportMenu from '@/components/ExportMenu.vue';
import PeriodPicker from '@/components/PeriodPicker.vue';
import RefineBox from '@/components/RefineBox.vue';
import RefinementBar from '@/components/RefinementBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Skeleton } from '@/components/ui/skeleton';
import VersionHistory from '@/components/VersionHistory.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { describeFilter } from '@/lib/labels';
import type {
    BreadcrumbItem,
    CurrentTenant,
    DashboardDetail,
    DashboardPeriodInfo,
    DashboardVersionEntry,
    ExportContext,
    RefinementState,
    TenantOption,
    VisualizationData,
} from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, Code2, Database, Sparkles, TriangleAlert } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    dashboard: DashboardDetail;
    period: DashboardPeriodInfo;
    refinement: RefinementState | null;
    versions: DashboardVersionEntry[];
    visualizationData?: VisualizationData | null;
    proposalData?: VisualizationData | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboards', href: '/dashboards' },
    { title: props.dashboard.title, href: `/dashboards/${props.dashboard.id}` },
]);

const createdAt = computed(() =>
    new Intl.DateTimeFormat('es', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(props.dashboard.created_at)),
);

const technicalSpec = computed(() => JSON.stringify(props.dashboard.dashboard_spec, null, 2));
const technicalDetails = computed(() =>
    [
        ['Proveedor', props.dashboard.provider],
        ['Modelo', props.dashboard.generation?.model],
        ['Contrato', props.dashboard.spec_version ? `DashboardSpec ${props.dashboard.spec_version}` : null],
    ].filter((detail): detail is [string, string] => Boolean(detail[1])),
);
// While a proposal is ready the page can show it in place of the live dashboard, with its own data.
const viewing = ref<'proposal' | 'current'>('proposal');
const previewing = computed(
    () =>
        props.refinement?.status === 'completed' &&
        !props.refinement.is_stale &&
        viewing.value === 'proposal' &&
        props.refinement.proposed_spec !== null,
);
const shownSpec = computed(() => (previewing.value ? props.refinement!.proposed_spec : props.dashboard.dashboard_spec));
const shownData = computed(() => (previewing.value ? props.proposalData : props.visualizationData));
const refinementRunning = computed(() => props.refinement?.status === 'queued' || props.refinement?.status === 'processing');
const refinementLocked = computed(() => props.refinement !== null && props.refinement.status !== 'failed');
watch(
    () => props.refinement?.id,
    () => (viewing.value = 'proposal'),
);

const page = usePage();
const actionError = computed(() => {
    const errors = page.props.errors as Record<string, string | undefined>;

    return errors.refinement ?? errors.version ?? errors.dashboard ?? null;
});
const resolving = ref(false);
const resolveOptions = { preserveScroll: true, onFinish: () => (resolving.value = false) };

const applyRefinement = () => {
    if (!props.refinement) return;

    resolving.value = true;
    router.post(route('dashboards.refinements.apply', { dashboard: props.dashboard.id, refinement: props.refinement.id }), {}, resolveOptions);
};
const discardRefinement = () => {
    if (!props.refinement) return;

    resolving.value = true;
    router.delete(route('dashboards.refinements.discard', { dashboard: props.dashboard.id, refinement: props.refinement.id }), resolveOptions);
};
const restoreVersion = (number: number) => {
    resolving.value = true;
    router.post(route('dashboards.versions.restore', { dashboard: props.dashboard.id, number }), {}, resolveOptions);
};

// A period replaces the dashboard's own date filters, so those chips would be misleading.
const visibleFilters = computed(() =>
    (shownSpec.value?.global_filters ?? []).filter((filter) => !(props.period.overrides_spec && filter.field === props.period.date_field)),
);

// What the period means in words, for the files that leave the app.
const periodCaption = computed(() => {
    if (props.period.overrides_spec) return props.period.label;

    return visibleFilters.value.map(describeFilter).join(' · ') || 'Todo el periodo';
});
// "Fecha: trimestre anterior" already names the field, so it reads "Periodo: trimestre anterior" on paper.
const printPeriod = computed(() =>
    periodCaption.value.startsWith('Fecha: ') ? periodCaption.value.replace('Fecha: ', 'Periodo: ') : `Periodo: ${periodCaption.value}`,
);
const exportContext = computed<ExportContext>(() => ({
    title: props.dashboard.title,
    description: props.dashboard.description,
    periodLabel: periodCaption.value,
}));

const refreshing = ref(false);
const selectPeriod = (value: string) => {
    router.get(route('dashboards.show', { dashboard: props.dashboard.id }), value === 'spec' ? {} : { period: value }, {
        only: ['period', 'visualizationData', 'proposalData'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (refreshing.value = true),
        onFinish: () => (refreshing.value = false),
    });
};

const isProcessing = computed(() => props.dashboard.status === 'queued' || props.dashboard.status === 'generating');

// A proposal that is still being prepared refreshes itself, like the dashboard does while it is generated.
let refinementTimer: ReturnType<typeof setInterval> | undefined;
const stopRefinementPolling = () => {
    if (refinementTimer !== undefined) clearInterval(refinementTimer);
    refinementTimer = undefined;
};
watch(
    refinementRunning,
    (running) => {
        stopRefinementPolling();

        if (running) refinementTimer = setInterval(() => router.reload({ only: ['refinement', 'proposalData', 'versions'] }), 2500);
    },
    { immediate: true },
);
onUnmounted(stopRefinementPolling);
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
                router.reload({ only: ['dashboard', 'visualizationData'] });
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
        <main class="mx-auto w-full max-w-[1400px] px-4 pb-20 pt-8 sm:px-6 lg:px-8 print:p-0">
            <Link
                href="/dashboards"
                class="motion-reveal -ml-1 inline-flex items-center gap-0.5 rounded-md px-1 text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring print:hidden"
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
                Dashboards
            </Link>

            <header class="motion-reveal-delayed mt-4">
                <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">{{ dashboard.title }}</h1>
                        <DashboardStatusBadge class="print:hidden" :status="dashboard.status" />
                    </div>
                    <DashboardActions class="print:hidden" :dashboard="dashboard" :versions="versions" />
                </div>
                <p v-if="dashboard.description" class="mt-2 max-w-3xl text-base leading-7 text-muted-foreground">{{ dashboard.description }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    <span>{{ createdAt }}</span>
                    <span v-if="dashboard.author">{{ dashboard.author.name }}</span>
                    <span class="inline-flex items-center gap-1.5"><Database class="size-3.5" aria-hidden="true" /> Datos de ejemplo</span>
                </div>
            </header>

            <section
                v-if="dashboard.original_prompt"
                class="motion-reveal-late mt-6 rounded-2xl bg-muted/70 px-5 py-4 print:hidden"
                aria-label="Pregunta original"
            >
                <p class="text-xs font-medium text-muted-foreground">Tu pregunta</p>
                <blockquote class="mt-1 whitespace-pre-wrap text-base leading-7 text-foreground">“{{ dashboard.original_prompt }}”</blockquote>
            </section>

            <section v-if="isProcessing" class="mt-8" aria-live="polite" aria-busy="true">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-primary-subtle text-primary">
                        <Sparkles class="size-[1.1rem] animate-pulse" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="font-semibold text-foreground">
                            {{ dashboard.status === 'queued' ? 'Preparando tu dashboard' : 'Diseñando el dashboard' }}
                        </h2>
                        <p class="text-sm text-muted-foreground">Esta página se actualiza sola cuando termine.</p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <Skeleton class="h-32 rounded-2xl" />
                    <Skeleton class="h-32 rounded-2xl" />
                    <Skeleton class="h-32 rounded-2xl" />
                    <Skeleton class="h-64 rounded-2xl md:col-span-3" />
                </div>
            </section>

            <section v-else-if="dashboard.status === 'failed'" class="mt-8 rounded-2xl border border-error/25 bg-error/10 p-5" role="alert">
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

            <div v-else-if="dashboard.status === 'archived'" class="mt-8 rounded-2xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Este dashboard proviene de una versión anterior y no contiene visualizaciones.</p>
            </div>

            <div v-else-if="shownSpec" class="mt-8">
                <p v-if="actionError" class="mb-4 rounded-xl border border-error/25 bg-error/10 px-4 py-3 text-sm text-foreground" role="alert">
                    {{ actionError }}
                </p>

                <RefineBox v-if="dashboard.can.refine" class="mb-5 print:hidden" :dashboard-id="dashboard.id" :locked="refinementLocked" />
                <RefinementBar
                    v-if="refinement"
                    v-model:viewing="viewing"
                    class="mb-5 print:hidden"
                    :refinement="refinement"
                    :busy="resolving"
                    @apply="applyRefinement"
                    @discard="discardRefinement"
                />

                <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <PeriodPicker class="print:hidden" :period="period" :busy="refreshing" @select="selectPeriod" />
                    <p class="hidden text-sm text-muted-foreground print:block">{{ printPeriod }}</p>
                    <div v-if="visibleFilters.length" class="flex flex-wrap items-center gap-2 print:hidden" aria-label="Filtros del dashboard">
                        <StatusBadge v-for="filter in visibleFilters" :key="`${filter.field}-${filter.operator}`">
                            {{ describeFilter(filter) }}
                        </StatusBadge>
                    </div>
                    <ExportMenu class="ml-auto" :spec="shownSpec" :data="shownData" :context="exportContext" :disabled="previewing" />
                </div>

                <div :class="['transition-opacity duration-200', refreshing ? 'opacity-50' : '']" :aria-busy="refreshing">
                    <DashboardCanvas :key="previewing ? 'proposal' : 'current'" :spec="shownSpec" :data="shownData" :export-context="exportContext" />
                </div>

                <VersionHistory
                    v-if="versions.length > 1"
                    class="mt-10 print:hidden"
                    :versions="versions"
                    :can-restore="dashboard.can.refine"
                    :busy="resolving"
                    @restore="restoreVersion"
                />

                <details class="mt-4 overflow-hidden rounded-2xl border bg-card print:hidden">
                    <summary
                        class="flex min-h-12 cursor-pointer list-none items-center gap-2 px-5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                    >
                        <Code2 class="size-4" aria-hidden="true" />
                        Detalles técnicos
                    </summary>
                    <dl v-if="technicalDetails.length" class="flex flex-wrap gap-x-8 gap-y-2 border-t px-5 py-4 text-sm">
                        <div v-for="[label, value] in technicalDetails" :key="label">
                            <dt class="text-xs text-muted-foreground">{{ label }}</dt>
                            <dd class="font-medium text-foreground">{{ value }}</dd>
                        </div>
                    </dl>
                    <pre
                        class="max-h-[28rem] overflow-auto border-t bg-muted/60 p-5 text-xs leading-5 text-foreground"
                    ><code>{{ technicalSpec }}</code></pre>
                </details>
            </div>
        </main>
    </AppLayout>
</template>
