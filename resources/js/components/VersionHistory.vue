<script setup lang="ts">
import { formatRelative } from '@/lib/format';
import type { DashboardVersionEntry } from '@/types';
import { History } from 'lucide-vue-next';

defineProps<{
    versions: DashboardVersionEntry[];
    canRestore: boolean;
    busy?: boolean;
}>();

const emit = defineEmits<{ restore: [number: number] }>();

const sourceLabel: Record<DashboardVersionEntry['source'], string> = {
    generated: 'Versión original',
    refined: 'Ajuste con IA',
    restored: 'Restauración',
};
</script>

<template>
    <details class="group overflow-hidden rounded-2xl border bg-card">
        <summary
            class="flex min-h-12 cursor-pointer list-none items-center gap-2 px-5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
        >
            <History class="size-4" aria-hidden="true" />
            Historial de versiones
            <span class="rounded-full bg-muted px-2 py-0.5 text-xs">{{ versions.length }}</span>
        </summary>
        <ol class="divide-y border-t">
            <li v-for="version in versions" :key="version.number" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3">
                <span class="w-14 shrink-0 text-sm font-semibold tabular-nums text-foreground">v{{ version.number }}</span>
                <div class="min-w-0 flex-1 basis-48">
                    <p class="text-sm text-foreground">
                        {{ sourceLabel[version.source] }}
                        <span v-if="version.is_current" class="ml-1.5 rounded-full bg-primary-subtle px-2 py-0.5 text-xs font-medium text-primary"
                            >Actual</span
                        >
                    </p>
                    <p v-if="version.instruction" class="truncate text-sm text-muted-foreground" :title="version.instruction">
                        <!-- Only what the user typed is a quotation; a restore note is system text. -->
                        <template v-if="version.source === 'refined'">«{{ version.instruction }}»</template>
                        <template v-else>{{ version.instruction }}</template>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatRelative(version.created_at) }}<template v-if="version.author"> · {{ version.author }}</template>
                    </p>
                </div>
                <button
                    v-if="canRestore && !version.is_current"
                    type="button"
                    :disabled="busy"
                    class="rounded-full border px-3 py-1 text-xs font-medium text-foreground transition-colors hover:border-primary/30 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                    @click="emit('restore', version.number)"
                >
                    Restaurar
                </button>
            </li>
        </ol>
    </details>
</template>
