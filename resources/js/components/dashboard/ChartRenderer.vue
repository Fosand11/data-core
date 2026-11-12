<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { buildChartOption, describeChart } from '@/lib/chartOptions';
import '@/lib/echarts';
import type { DashboardVisualization, VisualizationResult } from '@/types';
import { computed } from 'vue';
import VChart from 'vue-echarts';

const props = defineProps<{
    visualization: DashboardVisualization;
    result: VisualizationResult;
}>();

const theme = useChartTheme();
const option = computed(() => buildChartOption(props.visualization, props.result, theme.value));
const summary = computed(() => describeChart(props.visualization, props.result));
</script>

<template>
    <!-- The canvas is opaque to assistive tech: the label summarises it and the data table is the full alternative. -->
    <div role="img" :aria-label="summary" class="size-full">
        <VChart :option="option" autoresize class="size-full" />
    </div>
</template>
