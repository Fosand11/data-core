<script setup lang="ts">
import { formatCell } from '@/lib/format';
import { labelFor } from '@/lib/labels';
import type { VisualizationColumn, VisualizationResult } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    result: VisualizationResult;
    /** Column keys to show, in order. Defaults to every column of the result. */
    columns?: string[];
    caption?: string;
}>();

const visibleColumns = computed<VisualizationColumn[]>(() => {
    if (!props.columns?.length) return props.result.columns;

    return props.columns
        .map((key) => props.result.columns.find((column) => column.key === key))
        .filter((column): column is VisualizationColumn => column !== undefined);
});
</script>

<template>
    <div class="overflow-auto rounded-xl border">
        <table class="w-full text-left text-sm">
            <caption v-if="caption" class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead class="sticky top-0 bg-muted text-xs text-muted-foreground">
                <tr>
                    <th
                        v-for="column in visibleColumns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-3 font-medium"
                        :class="column.role === 'metric' ? 'text-right' : ''"
                    >
                        {{ column.type === 'month' ? 'Mes' : labelFor(column.key) }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in result.rows" :key="index" class="border-t">
                    <td
                        v-for="column in visibleColumns"
                        :key="column.key"
                        class="px-4 py-2.5"
                        :class="column.role === 'metric' ? 'text-right tabular-nums text-foreground' : 'text-foreground'"
                    >
                        {{ formatCell(row[column.key], column, result.meta?.current_month) }}
                    </td>
                </tr>
                <tr v-if="result.rows.length === 0">
                    <td :colspan="visibleColumns.length || 1" class="px-4 py-8 text-center text-xs text-muted-foreground">
                        No hay datos para esta consulta.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
