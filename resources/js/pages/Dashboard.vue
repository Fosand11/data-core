<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Building2, LayoutDashboard, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

interface TenantOption {
    name: string;
    slug: string;
    role: 'owner' | 'admin' | 'member';
}

interface DashboardItem {
    id: string;
    name: string;
    created_at: string;
}

const props = defineProps<{
    currentTenant: Pick<TenantOption, 'name' | 'slug'> | null;
    tenants: TenantOption[];
    dashboards: DashboardItem[];
}>();

const page = usePage<SharedData>();
const userName = computed(() => page.props.auth.user.name);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboards',
        href: '/dashboard',
    },
];

function selectTenant(event: Event): void {
    const slug = (event.target as HTMLSelectElement).value;

    if (slug && slug !== props.currentTenant?.slug) {
        router.patch(route('tenants.select', { tenant: slug }), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Dashboards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm text-muted-foreground">Hola, {{ userName }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Dashboards</h1>
                </div>

                <label v-if="tenants.length" class="grid gap-1.5 text-sm font-medium">
                    <span>Organización</span>
                    <span class="relative">
                        <Building2 class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <select
                            :value="currentTenant?.slug"
                            class="h-10 min-w-64 rounded-md border border-input bg-background pl-9 pr-8 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            aria-label="Seleccionar organización"
                            @change="selectTenant"
                        >
                            <option v-for="tenant in tenants" :key="tenant.slug" :value="tenant.slug">{{ tenant.name }} · {{ tenant.role }}</option>
                        </select>
                    </span>
                </label>
            </header>

            <section v-if="!currentTenant" class="rounded-xl border border-dashed p-10 text-center">
                <Building2 class="mx-auto size-10 text-muted-foreground" />
                <h2 class="mt-4 font-medium">No perteneces a una organización activa</h2>
                <p class="mt-2 text-sm text-muted-foreground">Solicita acceso a una organización para consultar dashboards.</p>
            </section>

            <section v-else class="flex flex-1 flex-col rounded-xl border bg-card">
                <div class="flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-medium">{{ currentTenant.name }}</h2>
                        <p class="text-sm text-muted-foreground">Dashboards de la organización actual</p>
                    </div>

                    <Button disabled class="gap-2" title="Disponible en la siguiente fase">
                        <Sparkles class="size-4" />
                        Crear dashboard con IA
                    </Button>
                </div>

                <div v-if="dashboards.length" class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="dashboard in dashboards" :key="dashboard.id" class="rounded-lg border p-4">
                        <LayoutDashboard class="size-5 text-muted-foreground" />
                        <h3 class="mt-3 font-medium">{{ dashboard.name }}</h3>
                    </article>
                </div>

                <div v-else class="grid flex-1 place-items-center p-10 text-center">
                    <div>
                        <LayoutDashboard class="mx-auto size-10 text-muted-foreground" />
                        <h3 class="mt-4 font-medium">Aún no hay dashboards</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                            La generación de dashboards mediante lenguaje natural se implementará en la siguiente fase.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
