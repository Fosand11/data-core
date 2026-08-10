<script setup lang="ts">
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import type { DashboardSummary } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, LayoutDashboard } from 'lucide-vue-next';
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
    <article class="group relative overflow-hidden rounded-xl border bg-card shadow-card transition-shadow hover:shadow-soft">
        <div class="flex h-32 items-center justify-center bg-gradient-to-br from-primary-subtle to-white" aria-hidden="true">
            <div class="flex size-14 items-center justify-center rounded-2xl border border-primary/10 bg-white text-primary shadow-sm">
                <LayoutDashboard class="size-7" />
            </div>
        </div>
        <div class="border-t p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-foreground">
                        <Link
                            :href="route('dashboards.show', { dashboard: dashboard.id })"
                            class="rounded-sm outline-none after:absolute after:inset-0 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ dashboard.title }}
                        </Link>
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">Creado el {{ createdAt }}</p>
                </div>
                <ArrowUpRight class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" aria-hidden="true" />
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <DashboardStatusBadge :status="dashboard.status" />
                <span v-if="dashboard.author" class="truncate text-xs text-muted-foreground">{{ dashboard.author.name }}</span>
            </div>
        </div>
    </article>
</template>
