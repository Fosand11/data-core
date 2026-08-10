<script setup lang="ts">
import DashboardCard from '@/components/DashboardCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import { buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, TenantOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutDashboard, Plus } from 'lucide-vue-next';

defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    dashboards: DashboardSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboards', href: '/dashboards' }];
</script>

<template>
    <Head title="Dashboards" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-[1440px] px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-primary">{{ currentTenant.name }}</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-foreground sm:text-h2">Dashboards</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Definiciones reales creadas dentro de la organización actual.</p>
                </div>
                <Link :href="route('dashboards.create')" :class="cn(buttonVariants(), 'w-full sm:w-auto')">
                    <Plus aria-hidden="true" />
                    Crear dashboard
                </Link>
            </header>

            <section v-if="dashboards.length" class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3" aria-label="Dashboards de la organización">
                <DashboardCard v-for="dashboard in dashboards" :key="dashboard.id" :dashboard="dashboard" />
            </section>

            <div v-else class="grid min-h-[55svh] place-items-center py-12">
                <EmptyState
                    title="Todavía no hay dashboards"
                    description="Crea la primera definición con el generador de demostración. No se consultarán datos ni servicios externos."
                    :icon="LayoutDashboard"
                >
                    <Link :href="route('dashboards.create')" :class="buttonVariants()">
                        <Plus aria-hidden="true" />
                        Crear dashboard
                    </Link>
                </EmptyState>
            </div>
        </main>
    </AppLayout>
</template>
