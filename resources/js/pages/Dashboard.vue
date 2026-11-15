<script setup lang="ts">
import DashboardCard from '@/components/DashboardCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import { MAX_PROMPT_LENGTH, useDashboardPrompt } from '@/composables/useDashboardPrompt';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CurrentTenant, DashboardSummary, SharedData, TenantOption } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUp, Building2, Database, LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
const today = computed(() => {
    const text = new Intl.DateTimeFormat('es', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

    return text.charAt(0).toUpperCase() + text.slice(1);
});

// Enter here creates the dashboard directly: no second screen, no second Enter.
const { form, canSubmit, submit, handleKeydown } = useDashboardPrompt();
const promptInput = ref<HTMLTextAreaElement | null>(null);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];
const suggestions = ['Ventas por región', 'Ingresos de los últimos meses', 'Productos con menor rendimiento', 'Rendimiento del equipo comercial'];

// A suggestion fills the box (so it can be tweaked) and leaves the cursor in it.
const selectSuggestion = (suggestion: string) => {
    form.prompt = suggestion;
    promptInput.value?.focus();
};

// Ready to type on arrival, but not on touch screens, where it would pop the keyboard.
onMounted(() => {
    if (window.matchMedia('(pointer: fine)').matches) promptInput.value?.focus();
});
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs" :current-tenant="currentTenant" :tenants="tenants">
        <main class="mx-auto w-full max-w-4xl px-4 pb-20 pt-10 sm:px-6 sm:pt-16">
            <div v-if="!currentTenant" class="grid place-items-center py-20">
                <EmptyState
                    title="No perteneces a una organización activa"
                    description="Solicita acceso a una organización para consultar y crear dashboards."
                    :icon="Building2"
                />
            </div>

            <template v-else>
                <header class="motion-reveal">
                    <p class="text-sm text-muted-foreground">{{ today }}</p>
                    <h1 class="mt-1 text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">{{ greeting }}, {{ firstName }}</h1>
                </header>

                <section class="motion-reveal-delayed mt-8" aria-label="Crear un dashboard">
                    <form class="insight-panel p-2 focus-within:border-primary/40" @submit.prevent="submit">
                        <label for="ai-prompt" class="sr-only">Describe el dashboard que necesitas</label>
                        <textarea
                            id="ai-prompt"
                            ref="promptInput"
                            v-model="form.prompt"
                            rows="3"
                            :maxlength="MAX_PROMPT_LENGTH"
                            :readonly="form.processing"
                            class="min-h-28 w-full resize-none border-0 bg-transparent px-4 pb-2 pt-4 text-lg leading-7 tracking-tight text-foreground outline-none placeholder:text-muted-foreground/70 read-only:opacity-70 focus:ring-0 sm:text-xl"
                            placeholder="¿Qué te gustaría entender hoy?"
                            :aria-invalid="form.errors.prompt ? 'true' : undefined"
                            @keydown="handleKeydown"
                        />
                        <div class="flex items-center justify-between gap-3 px-3 pb-2 pt-1">
                            <span class="inline-flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                                <Database class="size-3.5 shrink-0" aria-hidden="true" />
                                <span class="truncate">Datos de ejemplo · ventas de 12 meses</span>
                            </span>
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-all hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground"
                                aria-label="Crear dashboard"
                            >
                                <LoaderCircle v-if="form.processing" class="size-5 animate-spin" aria-hidden="true" />
                                <ArrowUp v-else class="size-5" aria-hidden="true" />
                            </button>
                        </div>
                    </form>
                    <InputError class="mt-2 px-2" :message="form.errors.prompt" />
                    <p v-if="form.processing" class="mt-3 px-2 text-sm text-muted-foreground" role="status">
                        Diseñando tu dashboard… puede tardar unos segundos.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2" role="list" aria-label="Sugerencias">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            role="listitem"
                            class="rounded-full border bg-card px-3.5 py-1.5 text-sm text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="selectSuggestion(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                </section>

                <section v-if="dashboards.length" class="motion-reveal-late mt-14" aria-labelledby="recent-title">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="recent-title" class="text-xl font-semibold tracking-tight text-foreground">Recientes</h2>
                        <Link
                            :href="route('dashboards.index')"
                            class="rounded-sm text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Ver todos
                        </Link>
                    </div>
                    <div class="dashboard-grid mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <DashboardCard v-for="dashboard in dashboards.slice(0, 6)" :key="dashboard.id" :dashboard="dashboard" />
                    </div>
                </section>
            </template>
        </main>
    </AppLayout>
</template>
