<script setup lang="ts">
import DashboardKpi from '@/components/dashboard/DashboardKpi.vue';
import DashboardTable from '@/components/dashboard/DashboardTable.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { LIGHT_CHART_THEME } from '@/composables/useChartTheme';
import { registerPrintPreparer } from '@/composables/usePrint';
import { showToast } from '@/composables/useToast';
import { hasPartialPeriod } from '@/lib/chartOptions';
import { composeImage, downloadBlob, downloadUrl, exportFilename, toCsv } from '@/lib/export';
import { describeFilter } from '@/lib/labels';
import { visualizationIcons } from '@/lib/visualizationIcons';
import type { DashboardVisualization, ExportContext, VisualizationResult } from '@/types';
import { Download, FileSpreadsheet, ImageDown } from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    visualization: DashboardVisualization;
    result?: VisualizationResult;
    exportContext?: ExportContext;
}>();

// ECharts is only downloaded when a dashboard actually draws a chart.
const ChartRenderer = defineAsyncComponent(() => import('@/components/dashboard/ChartRenderer.vue'));

const isChart = computed(() => ['bar', 'line', 'area', 'pie'].includes(props.visualization.type));
const partialPeriod = computed(() => isChart.value && props.result !== undefined && hasPartialPeriod(props.visualization, props.result));
// Filters that belong to this visualization only (for example a KPI about last month) ignore the page period.
const ownFilters = computed(() => props.visualization.query.filters.map(describeFilter).join(' · '));
const isEmpty = computed(() => props.result !== undefined && props.result.rows.length === 0);
const tableColumns = computed(() => {
    const columns = props.visualization.display.columns;

    return Array.isArray(columns) ? columns : undefined;
});

// A single number has little to export; charts and tables do.
const canExport = computed(() => props.visualization.type !== 'kpi' && props.result !== undefined && !isEmpty.value);

// The image already carries the visualization's title; repeat the dashboard's only when it says something else.
const subtitle = computed(() => {
    const dashboard = props.exportContext?.title;

    return [dashboard === props.visualization.title ? null : dashboard, props.exportContext?.periodLabel].filter(Boolean).join(' · ');
});
const fileParts = computed(() => [props.exportContext?.title ?? '', props.visualization.title]);

async function downloadImage() {
    if (!props.result) return;

    try {
        const { renderChartImage } = await import('@/lib/echarts');
        const chart = renderChartImage(props.visualization, props.result, { width: 1400, height: 720, theme: LIGHT_CHART_THEME, pixelRatio: 2 });
        const framed = await composeImage(chart, {
            title: props.visualization.title,
            subtitle: subtitle.value,
            footer: 'InsightFlow · Datos de ejemplo',
        });
        const filename = exportFilename(fileParts.value, 'png');

        downloadUrl(framed, filename);
        showToast(`Imagen descargada: ${filename}`);
    } catch {
        showToast('No se pudo generar la imagen. Inténtalo de nuevo.');
    }
}

function downloadCsv() {
    if (!props.result) return;

    const filename = exportFilename(fileParts.value, 'csv');

    downloadBlob(new Blob([toCsv(props.result, tableColumns.value)], { type: 'text/csv;charset=utf-8' }), filename);
    showToast(`Datos descargados: ${filename}`);
}

// For printing, a chart becomes a sharp static image in the light theme.
const printImage = ref<string | null>(null);
const printImg = ref<HTMLImageElement | null>(null);
const unregister = registerPrintPreparer(async () => {
    if (!isChart.value || !props.result || isEmpty.value) return;

    const { renderChartImage } = await import('@/lib/echarts');
    const columns = Math.max(1, props.visualization.layout.width);

    printImage.value = renderChartImage(props.visualization, props.result, {
        width: Math.round((1100 * columns) / 12),
        height: Math.max(240, props.visualization.layout.height * 50),
        theme: LIGHT_CHART_THEME,
        pixelRatio: 2,
    });
    await nextTick();
    await printImg.value?.decode().catch(() => undefined);
});
onBeforeUnmount(unregister);
</script>

<template>
    <article
        class="flex h-full min-h-0 flex-col overflow-hidden rounded-[1.25rem] border bg-card shadow-card print:break-inside-avoid print:shadow-none"
    >
        <header class="flex items-center justify-between gap-3 px-5 pt-4">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold text-foreground">{{ visualization.title }}</h3>
                <p v-if="ownFilters" class="mt-0.5 truncate text-xs text-muted-foreground" :title="ownFilters">{{ ownFilters }}</p>
            </div>
            <div v-if="visualization.type !== 'kpi'" class="flex shrink-0 items-center gap-1">
                <component :is="visualizationIcons[visualization.type]" class="size-4 text-muted-foreground" aria-hidden="true" />
                <DropdownMenu v-if="canExport">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="-mr-1.5 flex size-7 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring print:hidden"
                            :aria-label="`Descargar ${visualization.title}`"
                        >
                            <Download class="size-3.5" aria-hidden="true" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48 rounded-xl">
                        <DropdownMenuItem v-if="isChart" class="gap-2.5 rounded-lg" @select="downloadImage">
                            <ImageDown class="size-4 text-muted-foreground" aria-hidden="true" /> Imagen (PNG)
                        </DropdownMenuItem>
                        <DropdownMenuItem class="gap-2.5 rounded-lg" @select="downloadCsv">
                            <FileSpreadsheet class="size-4 text-muted-foreground" aria-hidden="true" /> Datos (CSV)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col px-5 pb-4 pt-3">
            <Skeleton v-if="!result" class="h-full min-h-24 w-full rounded-xl" />

            <p v-else-if="isEmpty && visualization.type !== 'table'" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                Sin datos para los filtros de este dashboard.
            </p>

            <DashboardKpi v-else-if="visualization.type === 'kpi'" :result="result" />

            <DashboardTable v-else-if="visualization.type === 'table'" :result="result" :columns="tableColumns" :caption="visualization.title" />

            <template v-else-if="isChart">
                <!-- The live canvas is for the screen; print swaps it for a static light-theme image. -->
                <div :class="['h-72 flex-none lg:h-auto lg:min-h-0 lg:flex-1', printImage ? 'print:hidden' : '']">
                    <ChartRenderer :visualization="visualization" :result="result" />
                </div>
                <img v-if="printImage" ref="printImg" :src="printImage" alt="" class="hidden h-auto w-full print:block" />
                <p v-if="partialPeriod" class="mt-2 text-xs text-muted-foreground">* Mes en curso: el último punto aún está incompleto.</p>
                <details class="group mt-3 border-t pt-3 print:hidden">
                    <summary
                        class="cursor-pointer list-none text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Ver tabla de datos
                    </summary>
                    <div class="mt-3 max-h-64">
                        <DashboardTable :result="result" :caption="`Datos de ${visualization.title}`" />
                    </div>
                </details>
            </template>
        </div>
    </article>
</template>
