<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import MetricCard from '@/components/MetricCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, SharedData, TenantOption } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ArrowRight, Building2, Database, LayoutDashboard, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    currentTenant: CurrentTenant | null;
    tenants: TenantOption[];
    dashboards: DashboardSummary[];
}>();

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.trim().split(/s+/)[0] || page.props.auth.user.name);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

const promptSuggestions = ['Ventas por región', 'Comparar ingresos con el periodo anterior', 'Productos con menor rendimiento'];
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="flex min-h-[calc(100svh-4.5rem)] min-w-0 flex-1 flex-col">
            <div v-if="!currentTenant" class="grid flex-1 place-items-center px-4 py-12 sm:px-8">
                <EmptyState
                    title="No perteneces a una organización activa"
                    description="Solicita acceso a una organización para consultar y crear dashboards."
                    :icon="Building2"
                />
            </div>

            <div v-else-if="dashboards.length === 0" class="flex flex-1 items-center px-4 py-12 sm:px-8 lg:py-16">
                <EmptyState
                    title="Tu primer dashboard comienza con una pregunta"
                    description="Cuando la generación esté disponible, podrás describir lo que necesitas y convertirlo en un dashboard para tu organización."
                    :icon="Database"
                >
                    <div class="mx-auto max-w-3xl rounded-2xl border bg-card p-5 text-left shadow-soft sm:p-6">
                        <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                            <Sparkles class="size-4" aria-hidden="true" />
                            InsightFlow AI Composer
                            <StatusBadge variant="primary" class="ml-auto">Disponible próximamente</StatusBadge>
                        </div>

                        <label for="ai-prompt" class="sr-only">Describe el dashboard que necesitas</label>
                        <textarea
                            id="ai-prompt"
                            rows="3"
                            class="mt-4 w-full resize-none rounded-xl border-0 bg-transparent p-0 text-base text-muted-foreground outline-none"
                            placeholder="Ej.: Muéstrame las ventas por región del último trimestre"
                            disabled
                        />

                        <div class="mt-5 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <span class="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                <Database class="size-4" aria-hidden="true" />
                                Las fuentes de datos estarán disponibles en una fase posterior
                            </span>
                            <AppButton size="sm" disabled title="Disponible próximamente">
                                Generar dashboard
                                <ArrowRight aria-hidden="true" />
                            </AppButton>
                        </div>
                    </div>

                    <div class="mx-auto mt-7 max-w-3xl text-left">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Ideas de ejemplo</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <button
                                v-for="suggestion in promptSuggestions"
                                :key="suggestion"
                                type="button"
                                class="min-h-14 rounded-xl border bg-card px-4 text-left text-sm font-medium text-foreground shadow-card disabled:cursor-not-allowed disabled:opacity-75"
                                disabled
                                title="Disponible próximamente"
                            >
                                <Sparkles class="mb-2 size-4 text-primary" aria-hidden="true" />
                                {{ suggestion }}
                            </button>
                        </div>
                    </div>
                </EmptyState>
            </div>

            <div v-else class="mx-auto w-full max-w-[1440px] px-4 py-8 sm:px-6 lg:px-8">
                <header>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-h2">Buenos días, {{ firstName }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Este es el resumen disponible de <strong class="font-semibold text-foreground">{{ currentTenant.name }}</strong
                        >.
                    </p>
                </header>

                <section class="mt-7 grid gap-4 md:grid-cols-3" aria-label="Métricas disponibles">
                    <MetricCard label="Dashboards" :value="dashboards.length" :icon="LayoutDashboard" />
                    <MetricCard label="Fuentes conectadas" value="Aún no disponible" :icon="Database" unavailable />
                    <MetricCard label="Consultas del periodo" value="Aún no disponible" :icon="Sparkles" unavailable />
                </section>

                <section class="mt-8 rounded-2xl bg-primary p-6 text-white shadow-soft sm:flex sm:items-center sm:justify-between sm:gap-8 lg:p-8">
                    <div>
                        <StatusBadge class="bg-white/15 text-white">Generación con IA · Próximamente</StatusBadge>
                        <h2 class="mt-3 text-xl font-semibold sm:text-h3">Crear dashboard con lenguaje natural</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">
                            La experiencia visual está preparada. La generación real se habilitará en una fase posterior.
                        </p>
                    </div>
                    <AppButton class="mt-5 shrink-0 bg-white text-primary hover:bg-primary-subtle sm:mt-0" disabled>
                        Crear con IA
                        <Sparkles aria-hidden="true" />
                    </AppButton>
                </section>

                <section class="mt-8" aria-labelledby="recent-dashboards-title">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <h2 id="recent-dashboards-title" class="text-lg font-semibold text-foreground">Dashboards recientes</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Información real de la organización seleccionada.</p>
                        </div>
                        <StatusBadge variant="primary">{{ dashboards.length }} en total</StatusBadge>
                    </div>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <DashboardCard v-for="dashboard in dashboards.slice(0, 6)" :key="dashboard.id" :dashboard="dashboard" />
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
