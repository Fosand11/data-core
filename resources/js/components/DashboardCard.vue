<script setup lang="ts">
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import type { DashboardSummary } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, BarChart3 } from 'lucide-vue-next';
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
    <article
        class="group relative overflow-hidden rounded-[1.35rem] border bg-card shadow-card transition-all duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-soft"
    >
        <div
            class="relative h-28 overflow-hidden border-b bg-[linear-gradient(135deg,hsl(var(--primary-subtle)),hsl(var(--card))_68%)]"
            aria-hidden="true"
        >
            <span class="absolute left-5 top-5 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-primary/55">Insight / board</span>
            <div class="absolute inset-x-5 bottom-4 flex h-12 items-end gap-1.5 opacity-70">
                <span class="h-[28%] flex-1 rounded-t-sm bg-primary/20 transition-all duration-500 group-hover:h-[46%]" />
                <span class="h-[62%] flex-1 rounded-t-sm bg-primary/35 transition-all duration-500 group-hover:h-[74%]" />
                <span class="h-[42%] flex-1 rounded-t-sm bg-primary/25 transition-all duration-500 group-hover:h-[58%]" />
                <span class="h-[82%] flex-1 rounded-t-sm bg-primary/65 transition-all duration-500 group-hover:h-[94%]" />
                <span class="h-[54%] flex-1 rounded-t-sm bg-primary/30 transition-all duration-500 group-hover:h-[68%]" />
            </div>
            <BarChart3
                class="absolute right-5 top-4 size-5 text-primary transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-110"
            />
        </div>
        <div class="p-5">
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
                    <p class="mt-1 text-xs text-muted-foreground">{{ createdAt }}</p>
                </div>
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full border text-muted-foreground transition-all duration-300 group-hover:border-primary/25 group-hover:bg-primary group-hover:text-white"
                >
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </span>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <DashboardStatusBadge :status="dashboard.status" />
                <span v-if="dashboard.author" class="truncate text-xs text-muted-foreground">{{ dashboard.author.name }}</span>
            </div>
        </div>
    </article>
</template>
