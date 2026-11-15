<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, DatasetColumnInfo, DatasetColumnType, DatasetDetail, DatasetLimits, TenantOption } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CircleAlert, LoaderCircle, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    dataset: DatasetDetail;
    columns: DatasetColumnInfo[];
    preview?: Array<Record<string, string | number | null>>;
    limits: DatasetLimits;
    can: { update: boolean; delete: boolean };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Datos', href: '/datasets' },
    { title: props.dataset.name, href: `/datasets/${props.dataset.id}` },
]);

const typeLabels: Record<DatasetColumnType, string> = { string: 'Texto', numeric: 'Número', datetime: 'Fecha' };
const typeNames: Record<DatasetColumnType, string> = { string: 'de texto', numeric: 'numéricas', datetime: 'de fecha' };
const number = (value: number) => value.toLocaleString('es');

// --- review (the dataset is waiting for the user to confirm how its columns were read) ---
const form = useForm({
    name: props.dataset.name,
    columns: props.columns.map((column) => ({ id: column.id, label: column.label, type: column.type, included: column.included })),
});

const samplesOf = (id: string) => props.columns.find((column) => column.id === id)?.samples ?? [];
const includedCount = computed(() => form.columns.filter((column) => column.included).length);
const used = computed(() => {
    const counts: Record<DatasetColumnType, number> = { string: 0, numeric: 0, datetime: 0 };

    form.columns.forEach((column) => {
        if (column.included) counts[column.type]++;
    });

    return counts;
});
const overLimit = computed(() => (Object.keys(used.value) as DatasetColumnType[]).filter((type) => used.value[type] > props.limits.slots[type]));
const canImport = computed(() => !form.processing && includedCount.value > 0 && overLimit.value.length === 0 && form.name.trim() !== '');

const importData = () => {
    if (!canImport.value) return;

    form.post(route('datasets.import', { dataset: props.dataset.id }), { preserveScroll: true });
};

// --- importing: the page checks back until the import ends ---
let timer: ReturnType<typeof setInterval> | undefined;

watch(
    () => props.dataset.status,
    (status) => {
        clearInterval(timer);
        timer = status === 'importing' ? setInterval(() => router.reload({ only: ['dataset', 'columns', 'preview'] }), 2000) : undefined;
    },
    { immediate: true },
);
onBeforeUnmount(() => clearInterval(timer));

// --- ready: a preview of the first rows ---
const previewColumns = computed(() => props.columns.filter((column) => column.included));
const cell = (column: DatasetColumnInfo, value: string | number | null | undefined): string => {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'number') return value.toLocaleString('en-US', { maximumFractionDigits: 2 });

    return column.type === 'datetime' && value.endsWith(' 00:00:00') ? value.slice(0, 10) : value;
};

// --- delete ---
const confirmingDelete = ref(false);
const deleting = ref(false);
const destroy = () => {
    deleting.value = true;
    router.delete(route('datasets.destroy', { dataset: props.dataset.id }), { onFinish: () => (deleting.value = false) });
};
const deleteMessage = computed(() => {
    const dashboards = props.dataset.dashboards_count;
    const extra = dashboards > 0 ? ` También se eliminarán los ${dashboards} dashboards creados con estos datos.` : '';

    return `«${props.dataset.name}» se eliminará de forma permanente, con todas sus filas.${extra} Esta acción no se puede deshacer.`;
});
</script>

<template>
    <Head :title="dataset.name" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-5xl px-4 pb-20 pt-8 sm:px-6 sm:pt-10">
            <Link
                :href="route('datasets.index')"
                class="inline-flex items-center gap-1 rounded-sm text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <ChevronLeft class="size-4" aria-hidden="true" /> Datos
            </Link>

            <header class="motion-reveal mt-4 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="truncate text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">{{ dataset.name }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ dataset.source_filename }}
                        <template v-if="dataset.status === 'ready'">
                            · {{ number(dataset.row_count) }} filas · {{ dataset.columns_count }} columnas</template
                        >
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <Link
                        v-if="dataset.status === 'ready'"
                        :href="route('dashboards.create', { dataset: dataset.id })"
                        :class="cn(buttonVariants(), 'gap-2 rounded-full px-5')"
                    >
                        <Sparkles class="size-4" aria-hidden="true" /> Preguntar sobre estos datos
                    </Link>
                    <button
                        v-if="can.delete"
                        type="button"
                        :class="cn(buttonVariants({ variant: 'outline' }), 'gap-2 rounded-full')"
                        @click="confirmingDelete = true"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        {{ dataset.status === 'pending' ? 'Cancelar' : 'Eliminar' }}
                    </button>
                </div>
            </header>

            <!-- 1. Review -->
            <section v-if="dataset.status === 'pending'" class="motion-reveal-delayed mt-8" aria-labelledby="review-title">
                <h2 id="review-title" class="text-xl font-semibold tracking-tight text-foreground">Revisa cómo entendimos tu archivo</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                    Leímos las primeras filas y adivinamos el tipo de cada columna. Corrige lo que haga falta y quita las columnas que no necesites.
                </p>

                <form class="mt-6" @submit.prevent="importData">
                    <div class="max-w-md">
                        <label for="dataset-name" class="text-sm font-medium text-foreground">Nombre de tus datos</label>
                        <input
                            id="dataset-name"
                            v-model="form.name"
                            type="text"
                            maxlength="120"
                            class="mt-1.5 w-full rounded-xl border bg-card px-3.5 py-2.5 text-sm text-foreground outline-none focus:border-primary/50 focus:ring-0"
                            :aria-invalid="form.errors.name ? 'true' : undefined"
                        />
                        <InputError class="mt-1.5" :message="form.errors.name" />
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-2xl border bg-card">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <caption class="sr-only">
                                Columnas encontradas en el archivo
                            </caption>
                            <thead class="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" class="w-14 px-4 py-3 font-medium">Usar</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Nombre de la columna</th>
                                    <th scope="col" class="w-36 px-4 py-3 font-medium">Tipo</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Ejemplos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(column, index) in form.columns" :key="column.id" :class="column.included ? '' : 'opacity-50'">
                                    <td class="px-4 py-2.5">
                                        <input
                                            v-model="column.included"
                                            type="checkbox"
                                            class="size-4 rounded border-border text-primary focus:ring-primary"
                                            :aria-label="`Usar la columna ${samplesOf(column.id)[0] ?? index + 1}`"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input
                                            v-model="column.label"
                                            type="text"
                                            maxlength="255"
                                            class="w-full rounded-lg border bg-background px-2.5 py-1.5 text-sm text-foreground outline-none focus:border-primary/50 focus:ring-0"
                                            :aria-label="`Nombre de la columna ${index + 1}`"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select
                                            v-model="column.type"
                                            class="w-full rounded-lg border bg-background py-1.5 pl-2.5 pr-8 text-sm text-foreground focus:border-primary/50 focus:ring-0"
                                            :aria-label="`Tipo de la columna ${column.label}`"
                                        >
                                            <option v-for="(label, type) in typeLabels" :key="type" :value="type">{{ label }}</option>
                                        </select>
                                    </td>
                                    <td class="max-w-[18rem] truncate px-4 py-2.5 text-muted-foreground">
                                        {{ samplesOf(column.id).join(' · ') || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError class="mt-3" :message="form.errors.columns" />
                    <p v-for="type in overLimit" :key="type" class="mt-3 text-sm text-error" role="alert">
                        Hay {{ used[type] }} columnas {{ typeNames[type] }} y el máximo es {{ limits.slots[type] }}. Quita algunas o cambia su tipo.
                    </p>
                    <p v-if="includedCount === 0" class="mt-3 text-sm text-error" role="alert">Selecciona al menos una columna.</p>

                    <p class="mt-4 text-sm text-muted-foreground">
                        Se importarán hasta {{ number(limits.max_rows) }} filas. Los valores que no encajen con el tipo elegido quedarán vacíos.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="!canImport" :class="cn(buttonVariants(), 'gap-2 rounded-full px-6')">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" />
                            Importar datos
                        </button>
                        <span class="text-sm text-muted-foreground">{{ includedCount }} de {{ form.columns.length }} columnas</span>
                    </div>
                </form>
            </section>

            <!-- 2. Importing -->
            <section v-else-if="dataset.status === 'importing'" class="motion-reveal-delayed mt-10" aria-live="polite" aria-busy="true">
                <div class="flex flex-col items-center rounded-2xl border bg-card px-6 py-14 text-center">
                    <LoaderCircle class="size-8 animate-spin text-primary" aria-hidden="true" />
                    <h2 class="mt-4 text-lg font-semibold text-foreground">Importando tus datos…</h2>
                    <p class="mt-1 max-w-md text-sm text-muted-foreground">
                        Puede tardar unos segundos. Esta página se actualiza sola cuando termine.
                    </p>
                </div>
            </section>

            <!-- 3. Failed -->
            <section v-else-if="dataset.status === 'failed'" class="motion-reveal-delayed mt-10" role="alert">
                <div class="flex flex-col items-center rounded-2xl border border-error/30 bg-card px-6 py-12 text-center">
                    <CircleAlert class="size-8 text-error" aria-hidden="true" />
                    <h2 class="mt-4 text-lg font-semibold text-foreground">No pudimos importar este archivo</h2>
                    <p class="mt-1 max-w-lg text-sm leading-6 text-muted-foreground">
                        {{ dataset.error_message ?? 'Ocurrió un error al leer el archivo.' }}
                    </p>
                    <Link :href="route('datasets.index')" :class="cn(buttonVariants(), 'mt-6 rounded-full px-5')">Subir otro archivo</Link>
                </div>
            </section>

            <!-- 4. Ready -->
            <section v-else class="motion-reveal-delayed mt-8" aria-labelledby="preview-title">
                <div
                    v-if="dataset.warning_count > 0"
                    class="mb-5 flex items-start gap-3 rounded-xl border border-warning/30 bg-amber-50 px-4 py-3 text-sm text-warning dark:bg-warning/10"
                    role="status"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>
                        {{ number(dataset.warning_count) }}
                        {{ dataset.warning_count === 1 ? 'valor no se pudo' : 'valores no se pudieron' }} interpretar con el tipo elegido y
                        {{ dataset.warning_count === 1 ? 'quedó vacío' : 'quedaron vacíos' }}.
                    </span>
                </div>

                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="preview-title" class="text-xl font-semibold tracking-tight text-foreground">Vista previa</h2>
                    <StatusBadge variant="success">Listo</StatusBadge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">Las primeras {{ preview?.length ?? 0 }} filas de {{ number(dataset.row_count) }}.</p>

                <div class="mt-4 max-h-[28rem] overflow-auto rounded-2xl border bg-card">
                    <table class="w-full min-w-max text-left text-sm">
                        <caption class="sr-only">
                            Primeras filas de
                            {{
                                dataset.name
                            }}
                        </caption>
                        <thead class="sticky top-0 border-b bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th
                                    v-for="column in previewColumns"
                                    :key="column.key"
                                    scope="col"
                                    :class="cn('whitespace-nowrap px-4 py-3 font-medium', column.type === 'numeric' && 'text-right')"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(row, index) in preview ?? []" :key="index">
                                <td
                                    v-for="column in previewColumns"
                                    :key="column.key"
                                    :class="
                                        cn('whitespace-nowrap px-4 py-2.5 text-foreground', column.type === 'numeric' && 'text-right tabular-nums')
                                    "
                                >
                                    {{ cell(column, row[column.key]) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <ConfirmDialog
            v-model:open="confirmingDelete"
            :title="dataset.status === 'pending' ? '¿Cancelar esta importación?' : '¿Eliminar estos datos?'"
            :description="dataset.status === 'pending' ? 'Se descartará el archivo que subiste.' : deleteMessage"
            :confirm-label="dataset.status === 'pending' ? 'Descartar' : 'Eliminar'"
            destructive
            :loading="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
