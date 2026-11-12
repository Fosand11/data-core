<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import FlashToast from '@/components/FlashToast.vue';
import type { BreadcrumbItemType, CurrentTenant, TenantOption } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    currentTenant?: CurrentTenant | null;
    tenants?: TenantOption[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    currentTenant: null,
    tenants: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppHeader :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants" />
            <slot />
        </AppContent>
        <CommandPalette />
        <FlashToast />
    </AppShell>
</template>
