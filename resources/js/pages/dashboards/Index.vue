<script setup lang="ts">
import DashboardCard from '@/components/DashboardCard.vue';
import { buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, TenantOption } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant;
    tenants: TenantOption[];
    dashboards: DashboardSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboards', href: '/dashboards' }];

const filter = ref('');
const fold = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const visible = computed(() => {
    const needle = fold(filter.value.trim());

    if (needle === '') return props.dashboards;

    return props.dashboards.filter((dashboard) => fold(`${dashboard.title} ${dashboard.description ?? ''}`).includes(needle));
});
</script>

<template>
    <Head title="Dashboards" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-6xl px-4 pb-20 pt-10 sm:px-6 sm:pt-14">
            <header class="motion-reveal flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Dashboards</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ dashboards.length === 0 ? 'Todavía no hay ninguno.' : `${dashboards.length} en ${currentTenant.name}` }}
                    </p>
                </div>
                <Link :href="route('dashboards.create')" :class="cn(buttonVariants(), 'rounded-full px-5')">
                    <Plus aria-hidden="true" />
                    Nueva pregunta
                </Link>
            </header>

            <section v-if="dashboards.length" class="motion-reveal-delayed mt-8" aria-label="Dashboards de la organización">
                <label class="relative block max-w-sm">
                    <span class="sr-only">Filtrar dashboards</span>
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input
                        v-model="filter"
                        type="search"
                        placeholder="Filtrar por título o descripción"
                        class="h-10 w-full rounded-full border border-border/80 bg-card pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-ring/30"
                    />
                </label>

                <div v-if="visible.length" class="dashboard-grid mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <DashboardCard v-for="dashboard in visible" :key="dashboard.id" :dashboard="dashboard" />
                </div>
                <p v-else class="mt-16 text-center text-sm text-muted-foreground" role="status">Ningún dashboard coincide con “{{ filter }}”.</p>
            </section>

            <div v-else class="motion-reveal-delayed mt-16 flex flex-col items-center text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-primary-subtle text-primary">
                    <LayoutGrid class="size-6" aria-hidden="true" />
                </span>
                <h2 class="mt-5 text-xl font-semibold tracking-tight text-foreground">Aún no hay dashboards</h2>
                <p class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                    Haz una pregunta en lenguaje natural y la convertimos en un dashboard con tus datos.
                </p>
                <Link :href="route('dashboards.create')" :class="cn(buttonVariants(), 'mt-6 rounded-full px-5')">
                    <Plus aria-hidden="true" /> Nueva pregunta
                </Link>
            </div>
        </main>
    </AppLayout>
</template>
