<script setup lang="ts">
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import { formatRelative } from '@/lib/format';
import { visualizationIcons, visualizationNames } from '@/lib/visualizationIcons';
import type { DashboardSummary } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Pin } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    dashboard: DashboardSummary;
}>();

const glyphs = computed(() => props.dashboard.visualization_types.slice(0, 4));
const meta = computed(() => [formatRelative(props.dashboard.created_at), props.dashboard.author?.name].filter(Boolean).join(' · '));
</script>

<template>
    <article
        class="group relative flex h-full flex-col rounded-2xl border bg-card p-5 shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-soft"
    >
        <div class="flex min-h-7 items-center gap-1.5">
            <span
                v-for="type in glyphs"
                :key="type"
                :title="visualizationNames[type]"
                class="flex size-7 items-center justify-center rounded-lg bg-primary-subtle text-primary"
            >
                <component :is="visualizationIcons[type]" class="size-3.5" aria-hidden="true" />
            </span>
            <div class="ml-auto flex items-center gap-2">
                <DashboardStatusBadge v-if="dashboard.status !== 'ready'" :status="dashboard.status" />
                <Pin v-if="dashboard.is_pinned" class="size-3.5 fill-primary text-primary" aria-label="Fijado" />
            </div>
        </div>

        <h3 class="mt-4 line-clamp-2 text-base font-semibold leading-snug tracking-tight text-foreground">
            <Link
                :href="route('dashboards.show', { dashboard: dashboard.id })"
                class="rounded-sm outline-none after:absolute after:inset-0 after:rounded-2xl focus-visible:after:ring-2 focus-visible:after:ring-ring"
            >
                {{ dashboard.title }}
            </Link>
        </h3>
        <p v-if="dashboard.description" class="mt-1.5 line-clamp-2 text-sm leading-5 text-muted-foreground">{{ dashboard.description }}</p>

        <p class="mt-auto pt-5 text-xs text-muted-foreground">{{ meta }}</p>
    </article>
</template>
