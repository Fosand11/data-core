<script setup lang="ts">
import MobileNavigation from '@/components/MobileNavigation.vue';
import TenantSwitcher from '@/components/TenantSwitcher.vue';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, TenantOption } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Bell, Plus, Search } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        currentTenant?: CurrentTenant | null;
        tenants?: TenantOption[];
    }>(),
    {
        breadcrumbs: () => [],
        currentTenant: null,
        tenants: () => [],
    },
);

const pageTitle = computed(() => props.breadcrumbs.at(-1)?.title ?? 'Inicio');
</script>

<template>
    <header
        class="sticky top-0 z-30 flex min-h-[4.5rem] shrink-0 flex-wrap items-center gap-3 border-b bg-card/95 px-4 backdrop-blur sm:px-6 min-[769px]:flex-nowrap"
    >
        <MobileNavigation />

        <div class="hidden min-w-0 items-center gap-3 min-[769px]:flex">
            <p class="truncate text-sm font-medium text-foreground">{{ pageTitle }}</p>
            <span v-if="tenants.length" class="h-5 w-px bg-border" aria-hidden="true" />
            <TenantSwitcher v-if="tenants.length" :current-tenant="currentTenant" :tenants="tenants" />
        </div>

        <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
            <div class="relative hidden lg:block">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <input
                    type="search"
                    class="h-10 w-72 rounded-lg border bg-background pl-9 pr-3 text-sm text-muted-foreground"
                    placeholder="Buscar dashboards, fuentes..."
                    aria-label="Búsqueda disponible próximamente"
                    title="Búsqueda disponible próximamente"
                    disabled
                />
            </div>

            <button
                type="button"
                class="relative flex size-11 items-center justify-center rounded-lg text-muted-foreground"
                aria-label="Notificaciones disponibles próximamente"
                title="Notificaciones disponibles próximamente"
                disabled
            >
                <Bell class="size-5" aria-hidden="true" />
            </button>

            <Link :href="route('dashboards.create')" :class="cn(buttonVariants(), 'hidden sm:inline-flex')">
                <Plus aria-hidden="true" />
                Crear dashboard
            </Link>
        </div>

        <div v-if="tenants.length" class="w-full border-t py-3 min-[769px]:hidden">
            <TenantSwitcher :current-tenant="currentTenant" :tenants="tenants" />
        </div>
    </header>
</template>
