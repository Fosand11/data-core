<script setup lang="ts">
import type { DashboardSummary } from '@/types';
import { LayoutDashboard } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    dashboard: DashboardSummary;
}>();

const createdAt = computed(() =>
    new Intl.DateTimeFormat('es', {
        dateStyle: 'medium',
    }).format(new Date(props.dashboard.created_at)),
);
</script>

<template>
    <article class="overflow-hidden rounded-xl border bg-card shadow-card">
        <div class="flex h-32 items-center justify-center bg-gradient-to-br from-primary-subtle to-white" aria-hidden="true">
            <div class="flex size-14 items-center justify-center rounded-2xl border border-primary/10 bg-white text-primary shadow-sm">
                <LayoutDashboard class="size-7" />
            </div>
        </div>
        <div class="border-t p-4">
            <h3 class="truncate font-semibold text-foreground">{{ dashboard.name }}</h3>
            <p class="mt-1 text-xs text-muted-foreground">Creado el {{ createdAt }}</p>
        </div>
    </article>
</template>
