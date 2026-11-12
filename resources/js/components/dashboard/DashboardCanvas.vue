<script setup lang="ts">
import VisualizationCard from '@/components/dashboard/VisualizationCard.vue';
import type { DashboardSpec, DashboardVisualization, ExportContext, VisualizationData } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    spec: DashboardSpec;
    data?: VisualizationData | null;
    exportContext?: ExportContext;
}>();

// Single column on small screens keeps reading order: top to bottom, then left to right.
const ordered = computed(() => [...props.spec.visualizations].sort((a, b) => a.layout.y - b.layout.y || a.layout.x - b.layout.x));

const placement = (visualization: DashboardVisualization) => ({
    '--col': `${visualization.layout.x + 1} / span ${visualization.layout.width}`,
    '--row': `${visualization.layout.y + 1} / span ${visualization.layout.height}`,
});
</script>

<template>
    <section aria-label="Visualizaciones del dashboard">
        <!-- The print variants keep the 12-column layout on paper, where the page is narrower than a desktop screen. -->
        <div
            class="grid grid-cols-1 gap-5 lg:auto-rows-[minmax(4.25rem,auto)] lg:grid-cols-12 print:auto-rows-[minmax(3.25rem,auto)] print:grid-cols-12 print:gap-3"
        >
            <div
                v-for="visualization in ordered"
                :key="visualization.key"
                :style="placement(visualization)"
                class="min-w-0 lg:[grid-column:var(--col)] lg:[grid-row:var(--row)] print:[grid-column:var(--col)] print:[grid-row:var(--row)]"
            >
                <VisualizationCard :visualization="visualization" :result="data?.[visualization.key]" :export-context="exportContext" />
            </div>
        </div>
    </section>
</template>
