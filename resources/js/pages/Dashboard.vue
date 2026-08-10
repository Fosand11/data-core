<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import MetricCard from '@/components/MetricCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, SharedData, TenantOption } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, Building2, CornerDownLeft, Database, LayoutDashboard, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    currentTenant: CurrentTenant | null;
    tenants: TenantOption[];
    dashboards: DashboardSummary[];
}>();

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.trim().split(/\s+/)[0] || page.props.auth.user.name);
const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';

    return 'Buenas noches';
});
const prompt = ref('');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

const promptSuggestions = ['Ventas por región', 'Comparar ingresos con el periodo anterior', 'Productos con menor rendimiento'];

const createDashboard = () => {
    if (prompt.value.trim().length < 10) return;

    router.get(route('dashboards.create'), { prompt: prompt.value.trim() });
};

const handlePromptKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

    event.preventDefault();
    createDashboard();
};
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

            <div
                v-else-if="dashboards.length === 0"
                class="mx-auto grid w-full max-w-[1480px] flex-1 gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,0.82fr)_minmax(32rem,1.18fr)] lg:items-center lg:px-8 lg:py-12"
            >
                <section class="motion-reveal lg:pr-6" aria-labelledby="empty-home-title">
                    <p class="editorial-kicker">Tu espacio está listo</p>
                    <h1
                        id="empty-home-title"
                        class="mt-5 max-w-xl text-4xl font-bold leading-[1.05] tracking-[-0.045em] text-foreground sm:text-5xl lg:text-[4rem]"
                    >
                        Una buena pregunta cambia la
                        <span class="relative whitespace-nowrap text-primary"
                            >perspectiva<span class="absolute -bottom-1 left-0 h-1 w-full rounded-full bg-primary/15" aria-hidden="true" /></span
                        >.
                    </h1>
                    <p class="mt-6 max-w-lg text-base leading-7 text-muted-foreground">
                        Escribe qué quieres comprender. InsightFlow convertirá tu intención en una definición analítica que podrás revisar antes de
                        conectarla a datos reales.
                    </p>
                    <div class="mt-8 flex items-center gap-3 text-xs text-muted-foreground">
                        <span class="flex size-8 items-center justify-center rounded-full border bg-card"
                            ><Database class="size-4 text-primary" aria-hidden="true"
                        /></span>
                        Modo demostración · sin consultas externas
                    </div>
                </section>

                <section class="motion-reveal-delayed" aria-label="Comenzar un dashboard">
                    <form class="insight-panel p-2" @submit.prevent="createDashboard">
                        <div
                            class="rounded-[1rem] bg-[linear-gradient(145deg,hsl(var(--primary-subtle)/0.75),hsl(var(--card))_65%)] px-5 py-5 sm:px-7 sm:py-7"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.15em] text-primary">
                                    <Sparkles class="size-4" aria-hidden="true" /> Pregunta inicial
                                </span>
                                <span class="text-xs text-muted-foreground">{{ currentTenant.name }}</span>
                            </div>
                            <label for="ai-prompt" class="sr-only">Describe el dashboard que necesitas</label>
                            <textarea
                                id="ai-prompt"
                                v-model="prompt"
                                rows="3"
                                maxlength="2000"
                                class="mt-5 min-h-28 w-full resize-none border-0 bg-transparent p-0 text-xl font-medium leading-8 tracking-tight text-foreground outline-none placeholder:font-normal placeholder:text-muted-foreground/70 focus:ring-0 sm:text-2xl"
                                placeholder="¿Qué te gustaría entender hoy?"
                                @keydown="handlePromptKeydown"
                            />
                            <div class="mt-4 flex flex-col gap-3 border-t border-primary/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <span class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                                    ><CornerDownLeft class="size-3.5" aria-hidden="true" /> Enter para continuar</span
                                >
                                <AppButton class="rounded-full px-5" size="sm" type="submit" :disabled="prompt.trim().length < 10">
                                    Darle forma <ArrowRight aria-hidden="true" />
                                </AppButton>
                            </div>
                        </div>
                    </form>

                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="(suggestion, index) in promptSuggestions"
                            :key="suggestion"
                            type="button"
                            class="group flex min-h-20 items-start gap-3 rounded-2xl border border-transparent px-4 py-3 text-left text-sm text-muted-foreground transition-all duration-300 hover:border-primary/15 hover:bg-card hover:text-foreground hover:shadow-card"
                            @click="prompt = suggestion"
                        >
                            <span class="mt-0.5 text-[0.65rem] font-bold text-primary/55">0{{ index + 1 }}</span>
                            <span>{{ suggestion }}</span>
                        </button>
                    </div>
                </section>
            </div>

            <div v-else class="mx-auto w-full max-w-[1480px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <header class="motion-reveal flex flex-col gap-6 border-b border-border/70 pb-7 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="editorial-kicker">Pulso del espacio</p>
                        <h1 class="mt-4 text-3xl font-bold tracking-[-0.04em] text-foreground sm:text-4xl">{{ greeting }}, {{ firstName }}.</h1>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Una lectura honesta de lo disponible en <strong class="font-semibold text-foreground">{{ currentTenant.name }}</strong
                            >.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 sm:text-right">
                        <span class="text-5xl font-bold tracking-[-0.06em] text-primary">{{ dashboards.length }}</span>
                        <span class="max-w-20 text-xs font-semibold uppercase leading-4 tracking-[0.12em] text-muted-foreground"
                            >dashboards reales</span
                        >
                    </div>
                </header>

                <section class="motion-reveal-delayed mt-7 grid gap-4 md:grid-cols-3" aria-label="Métricas disponibles">
                    <MetricCard label="Dashboards" :value="dashboards.length" :icon="LayoutDashboard" />
                    <MetricCard label="Fuentes conectadas" value="Aún no disponible" :icon="Database" unavailable />
                    <MetricCard label="Consultas del periodo" value="Aún no disponible" :icon="Sparkles" unavailable />
                </section>

                <section
                    class="motion-reveal-late relative mt-8 overflow-hidden rounded-[1.5rem] bg-[#17162f] p-6 text-white shadow-soft sm:flex sm:items-center sm:justify-between sm:gap-8 lg:p-8"
                >
                    <div class="absolute -right-12 -top-20 size-56 rounded-full border border-indigo-300/15" aria-hidden="true" />
                    <div class="absolute -right-2 -top-8 size-32 rounded-full border border-indigo-300/10" aria-hidden="true" />
                    <div class="relative">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-indigo-300">Abrir una nueva línea de análisis</p>
                        <h2 class="mt-3 max-w-xl text-xl font-semibold tracking-tight sm:text-h3">
                            No empieces por una gráfica. Empieza por lo que quieres comprender.
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/70">
                            El estudio de demostración transforma tu pregunta en una estructura validada, sin consultar todavía servicios externos.
                        </p>
                    </div>
                    <AppButton
                        class="relative mt-5 shrink-0 rounded-full bg-white px-5 text-primary hover:bg-primary-subtle sm:mt-0"
                        @click="router.get(route('dashboards.create'))"
                    >
                        Nueva pregunta <ArrowUpRight aria-hidden="true" />
                    </AppButton>
                </section>

                <section class="mt-10" aria-labelledby="recent-dashboards-title">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="editorial-kicker">Archivo reciente</p>
                            <h2 id="recent-dashboards-title" class="mt-2 text-xl font-semibold tracking-tight text-foreground">
                                Lo último que has estado explorando
                            </h2>
                        </div>
                        <StatusBadge variant="primary">{{ dashboards.length }} en total</StatusBadge>
                    </div>

                    <div class="dashboard-grid mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <DashboardCard v-for="dashboard in dashboards.slice(0, 6)" :key="dashboard.id" :dashboard="dashboard" />
                    </div>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
