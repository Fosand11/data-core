<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
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
    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <slot />
    </AppLayout>
</template>
