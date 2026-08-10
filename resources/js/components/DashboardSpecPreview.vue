<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { DashboardMetric, DashboardSpec, DashboardVisualization } from '@/types';
import { BarChart3, Hash, LineChart, Table2 } from 'lucide-vue-next';

defineProps<{
    spec: DashboardSpec;
}>();

const metricLabel = (metric: DashboardMetric) => `${metric.aggregation}(${metric.field}) → ${metric.alias}`;
const primaryMetricLabel = (visualization: DashboardVisualization) => {
    const metric = visualization.query.metrics[0];

    return metric ? metricLabel(metric) : 'Sin métrica configurada';
};
const tableColumns = (visualization: DashboardVisualization) => {
    const columns = visualization.display.columns;

    return Array.isArray(columns) ? columns : [];
};

const visualizationIcon = (visualization: DashboardVisualization) => {
    if (visualization.type === 'kpi') return Hash;
    if (visualization.type === 'bar') return BarChart3;
    if (visualization.type === 'line') return LineChart;

    return Table2;
};
</script>

<template>
    <section aria-labelledby="spec-preview-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="spec-preview-title" class="text-xl font-semibold text-foreground">Vista previa de la definición</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Dataset <code class="rounded bg-muted px-1.5 py-0.5 text-foreground">{{ spec.dataset }}</code> · No se han consultado datos.
                </p>
            </div>
            <StatusBadge variant="primary">DashboardSpec {{ spec.version }}</StatusBadge>
        </div>

        <div v-if="spec.global_filters.length" class="mt-5 rounded-xl border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Filtros globales</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <StatusBadge v-for="filter in spec.global_filters" :key="`${filter.field}-${filter.operator}`">
                    {{ filter.field }} · {{ filter.operator }}
                </StatusBadge>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <article
                v-for="visualization in spec.visualizations"
                :key="visualization.key"
                class="overflow-hidden rounded-xl border bg-card shadow-card"
            >
                <header class="flex items-start justify-between gap-4 border-b p-5">
                    <div>
                        <h3 class="font-semibold text-foreground">{{ visualization.title }}</h3>
                        <p class="mt-1 text-xs text-muted-foreground">Clave: {{ visualization.key }}</p>
                    </div>
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-subtle text-primary">
                        <component :is="visualizationIcon(visualization)" class="size-5" aria-hidden="true" />
                    </div>
                </header>

                <div class="p-5">
                    <div v-if="visualization.type === 'kpi'" class="rounded-xl border border-dashed bg-background p-6 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Métrica configurada</p>
                        <p class="mt-3 font-mono text-sm font-semibold text-foreground">
                            {{ primaryMetricLabel(visualization) }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">El valor estará disponible cuando exista una fuente de datos.</p>
                    </div>

                    <div
                        v-else-if="visualization.type === 'bar' || visualization.type === 'line'"
                        class="flex min-h-44 flex-col items-center justify-center rounded-xl border border-dashed bg-background p-6 text-center"
                    >
                        <component :is="visualizationIcon(visualization)" class="size-9 text-primary" aria-hidden="true" />
                        <p class="mt-3 text-sm font-semibold text-foreground">
                            Placeholder de {{ visualization.type === 'bar' ? 'barras' : 'línea' }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Dimensión: <code>{{ visualization.query.dimensions[0]?.field }}</code
                            ><br />
                            Métrica: <code>{{ primaryMetricLabel(visualization) }}</code>
                        </p>
                    </div>

                    <div v-else class="overflow-hidden rounded-xl border">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-muted/70 text-xs text-muted-foreground">
                                    <tr>
                                        <th v-for="column in tableColumns(visualization)" :key="column" scope="col" class="px-4 py-3 font-medium">
                                            {{ column }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td :colspan="tableColumns(visualization).length" class="px-4 py-8 text-center text-xs text-muted-foreground">
                                            Sin filas: esta fase sólo define la estructura.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <StatusBadge v-for="metric in visualization.query.metrics" :key="metric.alias" variant="info">
                            {{ metricLabel(metric) }}
                        </StatusBadge>
                        <StatusBadge v-for="dimension in visualization.query.dimensions" :key="dimension.field">
                            dimensión: {{ dimension.field }}
                        </StatusBadge>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
