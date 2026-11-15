<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, DatasetLimits, DatasetStatus, DatasetSummary, TenantOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Database, FileSpreadsheet, FileText, LoaderCircle, ShieldCheck, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    datasets: DatasetSummary[];
    limits: DatasetLimits;
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Datos', href: '/datasets' }];

const form = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const clientError = ref<string | null>(null);

const maxMb = computed(() => Math.round((props.limits.max_file_kb / 1024) * 10) / 10);
const atLimit = computed(() => props.datasets.length >= props.limits.max_datasets);
const error = computed(() => clientError.value ?? form.errors.file ?? null);
const number = (value: number) => value.toLocaleString('es');

const statuses: Record<DatasetStatus, { label: string; variant: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info' }> = {
    pending: { label: 'Por revisar', variant: 'warning' },
    importing: { label: 'Importando', variant: 'info' },
    ready: { label: 'Listo', variant: 'success' },
    failed: { label: 'No se pudo importar', variant: 'error' },
};

// Cheap checks first, so an obviously wrong file never travels; the server checks again.
const upload = (file: File | undefined) => {
    clientError.value = null;
    form.clearErrors();

    if (!file || atLimit.value) return;

    if (!/\.(csv|txt|xlsx)$/i.test(file.name)) {
        clientError.value = 'Solo se aceptan archivos CSV o Excel (.xlsx). Si tu archivo es .xls, guárdalo como .xlsx o como CSV.';
        return;
    }

    if (file.size > props.limits.max_file_kb * 1024) {
        clientError.value = `El archivo supera el máximo de ${maxMb.value} MB.`;
        return;
    }

    form.file = file;
    form.post(route('datasets.store'), { forceFormData: true, onFinish: () => (form.file = null) });
};

const onPick = (event: Event) => {
    const input = event.target as HTMLInputElement;

    upload(input.files?.[0]);
    input.value = '';
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    upload(event.dataTransfer?.files?.[0]);
};
</script>

<template>
    <Head title="Datos" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-5xl px-4 pb-20 pt-10 sm:px-6 sm:pt-14">
            <header class="motion-reveal">
                <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Tus datos</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-muted-foreground">
                    Sube un archivo CSV o Excel y pregúntale lo que quieras. Los números se calculan en el servidor, a partir de tus filas.
                </p>
            </header>

            <section v-if="can.create" class="motion-reveal-delayed mt-8" aria-label="Subir un archivo">
                <div
                    :class="
                        cn(
                            'relative rounded-2xl border-2 border-dashed bg-card px-6 py-10 text-center transition-colors',
                            dragging ? 'border-primary bg-primary-subtle' : 'border-border',
                            atLimit && 'opacity-60',
                        )
                    "
                    @dragover.prevent="dragging = !atLimit"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        class="sr-only"
                        accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        aria-label="Elegir un archivo CSV o Excel"
                        :disabled="atLimit || form.processing"
                        @change="onPick"
                    />

                    <div v-if="form.processing" class="flex flex-col items-center gap-3" role="status">
                        <LoaderCircle class="size-8 animate-spin text-primary" aria-hidden="true" />
                        <p class="text-sm font-medium text-foreground">Subiendo y leyendo tu archivo…</p>
                        <p v-if="form.progress" class="text-xs text-muted-foreground">{{ form.progress.percentage }} %</p>
                    </div>

                    <div v-else class="flex flex-col items-center gap-3">
                        <span class="flex size-12 items-center justify-center rounded-full bg-primary-subtle text-primary">
                            <Upload class="size-6" aria-hidden="true" />
                        </span>
                        <div>
                            <p class="text-base font-medium text-foreground">Arrastra tu archivo aquí</p>
                            <p class="mt-1 text-sm text-muted-foreground">CSV o Excel (.xlsx)</p>
                        </div>
                        <button type="button" :disabled="atLimit" :class="cn(buttonVariants(), 'rounded-full px-5')" @click="fileInput?.click()">
                            Elegir archivo
                        </button>
                        <p class="text-xs text-muted-foreground">
                            Hasta {{ maxMb }} MB · {{ number(limits.max_rows) }} filas · {{ limits.max_columns }} columnas
                        </p>
                    </div>
                </div>

                <InputError class="mt-3 px-1" :message="error ?? undefined" />
                <p v-if="atLimit" class="mt-3 px-1 text-sm text-muted-foreground" role="status">
                    Tu organización ya tiene {{ limits.max_datasets }} conjuntos de datos, que es el máximo. Elimina alguno para subir otro.
                </p>

                <p class="mt-4 flex items-start gap-2 px-1 text-sm text-muted-foreground">
                    <ShieldCheck class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                    <span>La IA solo recibe los nombres de tus columnas, nunca tus filas. Tus datos son privados de tu organización.</span>
                </p>
                <p class="mt-2 px-1 text-sm text-muted-foreground">
                    Esta es una demostración: no subas datos sensibles (salud, financieros, contraseñas, documentos de identidad) ni información de
                    terceros que no tengas derecho a tratar. El archivo original se elimina al terminar la importación y puedes borrar tus datos
                    cuando quieras.
                    <a :href="route('privacy')" target="_blank" rel="noopener" class="font-medium text-primary hover:underline"
                        >Política de privacidad</a
                    >
                </p>
            </section>

            <section class="motion-reveal-late mt-10" aria-labelledby="datasets-title">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="datasets-title" class="text-xl font-semibold tracking-tight text-foreground">Tus conjuntos de datos</h2>
                    <span class="text-sm text-muted-foreground">{{ datasets.length }} de {{ limits.max_datasets }}</span>
                </div>

                <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="dataset in datasets" :key="dataset.id">
                        <Link
                            :href="route('datasets.show', { dataset: dataset.id })"
                            class="group flex h-full flex-col rounded-2xl border bg-card p-5 shadow-card transition-colors hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary">
                                    <FileSpreadsheet v-if="dataset.source_type === 'xlsx'" class="size-5" aria-hidden="true" />
                                    <FileText v-else class="size-5" aria-hidden="true" />
                                </span>
                                <StatusBadge :variant="statuses[dataset.status].variant">{{ statuses[dataset.status].label }}</StatusBadge>
                            </div>
                            <h3 class="mt-4 truncate text-base font-semibold text-foreground">{{ dataset.name }}</h3>
                            <p class="mt-1 truncate text-sm text-muted-foreground">{{ dataset.source_filename }}</p>
                            <p class="mt-4 text-sm text-muted-foreground">
                                <template v-if="dataset.status === 'ready'">
                                    {{ number(dataset.row_count) }} filas · {{ dataset.columns_count }} columnas
                                    <template v-if="dataset.dashboards_count"> · {{ dataset.dashboards_count }} dashboards</template>
                                </template>
                                <template v-else-if="dataset.status === 'pending'">Revisa cómo entendimos tus columnas</template>
                                <template v-else-if="dataset.status === 'importing'">Importando tus datos…</template>
                                <template v-else>Abre para ver qué pasó</template>
                            </p>
                            <span class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-medium text-primary">
                                Abrir <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                            </span>
                        </Link>
                    </li>

                    <li>
                        <Link
                            :href="route('dashboards.create')"
                            class="group flex h-full flex-col rounded-2xl border border-dashed bg-card p-5 transition-colors hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <span class="flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                <Database class="size-5" aria-hidden="true" />
                            </span>
                            <h3 class="mt-4 text-base font-semibold text-foreground">Datos de ejemplo</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Ventas de 12 meses, incluidas en tu organización para probar.</p>
                            <span class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-medium text-primary">
                                Preguntar <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </main>
    </AppLayout>
</template>
