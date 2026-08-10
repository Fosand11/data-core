<script setup lang="ts">
import DashboardCard from '@/components/DashboardCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import { buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, TenantOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, LayoutDashboard, Plus } from 'lucide-vue-next';

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
        <main class="mx-auto w-full max-w-[1480px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <header class="motion-reveal grid gap-7 border-b border-border/70 pb-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <p class="editorial-kicker">Archivo analítico · {{ currentTenant.name }}</p>
                    <h1 class="mt-4 max-w-3xl text-4xl font-bold tracking-[-0.045em] text-foreground sm:text-5xl">
                        Las preguntas que ya tomaron forma.
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-muted-foreground">
                        Cada pieza conserva su solicitud original y la estructura validada para esta organización.
                    </p>
                </div>
                <Link
                    :href="route('dashboards.create')"
                    :class="
                        cn(
                            buttonVariants(),
                            'w-full rounded-full px-5 shadow-[0_8px_22px_-10px_hsl(var(--primary))] transition-transform duration-300 hover:-translate-y-0.5 sm:w-auto',
                        )
                    "
                >
                    <Plus aria-hidden="true" />
                    Nueva pregunta
                </Link>
            </header>

            <section v-if="dashboards.length" class="mt-8" aria-label="Dashboards de la organización">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">
                        {{ dashboards.length }} {{ dashboards.length === 1 ? 'pieza' : 'piezas' }} en el archivo
                    </p>
                    <span class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                        >Abrir para revisar <ArrowUpRight class="size-3.5" aria-hidden="true"
                    /></span>
                </div>
                <div class="dashboard-grid grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <DashboardCard v-for="dashboard in dashboards" :key="dashboard.id" :dashboard="dashboard" />
                </div>
            </section>

            <div v-else class="motion-reveal-delayed grid min-h-[48svh] place-items-center py-12">
                <div class="insight-panel grid w-full max-w-4xl gap-8 p-6 sm:p-8 lg:grid-cols-[0.7fr_1.3fr] lg:items-center">
                    <div class="relative flex min-h-48 items-center justify-center overflow-hidden rounded-2xl bg-primary-subtle" aria-hidden="true">
                        <span class="absolute left-5 top-4 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-primary/50">Archivo / 00</span>
                        <span class="size-28 rounded-full border border-primary/15" />
                        <span class="absolute size-16 rotate-45 rounded-2xl border border-primary/30" />
                        <LayoutDashboard class="absolute size-7 text-primary" />
                    </div>
                    <EmptyState
                        title="Un archivo esperando su primera pregunta"
                        description="Crea una definición con el estudio de demostración. No se consultarán datos ni servicios externos."
                        :icon="undefined"
                    >
                        <Link :href="route('dashboards.create')" :class="cn(buttonVariants(), 'rounded-full px-5')">
                            <Plus aria-hidden="true" /> Nueva pregunta
                        </Link>
                    </EmptyState>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
