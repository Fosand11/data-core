<script setup lang="ts">
import { formatValue } from '@/lib/format';
import type { VisualizationResult } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    result: VisualizationResult;
}>();

const metric = computed(() => props.result.columns.find((column) => column.role === 'metric'));
const value = computed(() => (metric.value ? (props.result.rows[0]?.[metric.value.key] ?? null) : null));
// A headline number reads best in full; only millions and above are shortened.
const display = computed(() => formatValue(value.value, metric.value?.format, { compact: Math.abs(Number(value.value)) >= 1_000_000 }));
</script>

<template>
    <div class="flex h-full flex-col justify-center">
        <p class="text-3xl font-semibold tabular-nums tracking-tight text-foreground sm:text-4xl">{{ display }}</p>
    </div>
</template>
