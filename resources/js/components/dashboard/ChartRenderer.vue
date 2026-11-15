<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { buildChartOption, describeChart } from '@/lib/chartOptions';
import '@/lib/echarts';
import type { ChartPaletteOption, DashboardVisualization, VisualizationResult } from '@/types';
import { computed } from 'vue';
import VChart from 'vue-echarts';

const props = defineProps<{
    visualization: DashboardVisualization;
    result: VisualizationResult;
    /** The dashboard's palette and the slot this chart uses; the defaults draw the app's own colours. */
    palette?: ChartPaletteOption;
    accent?: number;
}>();

const theme = useChartTheme(() => props.palette);
const option = computed(() => buildChartOption(props.visualization, props.result, theme.value, props.accent));
const summary = computed(() => describeChart(props.visualization, props.result));
</script>

<template>
    <!-- The canvas is opaque to assistive tech: the label summarises it and the data table is the full alternative. -->
    <div role="img" :aria-label="summary" class="size-full">
        <VChart :option="option" autoresize class="size-full" />
    </div>
</template>
