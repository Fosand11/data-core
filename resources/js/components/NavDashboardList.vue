<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { DashboardStatus, RecentDashboard } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title: string;
    dashboards: RecentDashboard[];
}>();

const page = usePage();
const pagePath = computed(() => page.url.split('?')[0]);

const dotClass: Record<DashboardStatus, string> = {
    queued: 'bg-warning',
    generating: 'bg-info',
    ready: 'bg-primary/60',
    failed: 'bg-error',
    archived: 'bg-muted-foreground/40',
};
</script>

<template>
    <SidebarGroup v-if="dashboards.length" class="p-0 group-data-[collapsible=icon]:hidden">
        <SidebarGroupLabel class="h-7 px-2.5 text-xs font-medium text-muted-foreground">{{ title }}</SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="dashboard in dashboards" :key="dashboard.id">
                <SidebarMenuButton
                    as-child
                    :is-active="pagePath === `/dashboards/${dashboard.id}`"
                    class="h-8 rounded-lg px-2.5 text-[0.85rem] text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-foreground"
                >
                    <Link :href="route('dashboards.show', { dashboard: dashboard.id })" :title="dashboard.title">
                        <span :class="['size-1.5 shrink-0 rounded-full', dotClass[dashboard.status]]" aria-hidden="true" />
                        <span class="truncate">{{ dashboard.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
