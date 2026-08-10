<script setup lang="ts">
import MobileNavigation from '@/components/MobileNavigation.vue';
import TenantSwitcher from '@/components/TenantSwitcher.vue';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant, TenantOption } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Command, Plus, Search } from 'lucide-vue-next';
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
        class="sticky top-0 z-30 flex min-h-[4.5rem] shrink-0 flex-wrap items-center gap-3 border-b border-border/70 bg-background/85 px-4 backdrop-blur-xl sm:px-6 min-[769px]:flex-nowrap"
    >
        <MobileNavigation />

        <div class="hidden min-w-0 items-center gap-4 min-[769px]:flex">
            <div class="min-w-0">
                <span class="block text-[0.6rem] font-bold uppercase tracking-[0.18em] text-muted-foreground">Vista actual</span>
                <p class="truncate text-sm font-semibold text-foreground">{{ pageTitle }}</p>
            </div>
            <span v-if="tenants.length" class="h-7 w-px bg-border" aria-hidden="true" />
            <TenantSwitcher v-if="tenants.length" :current-tenant="currentTenant" :tenants="tenants" />
        </div>

        <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
            <div class="relative hidden lg:block">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <input
                    type="search"
                    class="h-10 w-64 rounded-full border border-border/80 bg-card/70 pl-9 pr-12 text-sm text-muted-foreground shadow-[inset_0_1px_0_rgb(255_255_255/0.8)] transition-colors hover:border-primary/25"
                    placeholder="Buscar en el estudio..."
                    aria-label="Búsqueda disponible próximamente"
                    title="Búsqueda disponible próximamente"
                    disabled
                />
                <span
                    class="pointer-events-none absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center gap-0.5 rounded-md border bg-background px-1.5 py-1 text-[0.6rem] font-semibold text-muted-foreground"
                >
                    <Command class="size-2.5" aria-hidden="true" /> K
                </span>
            </div>

            <Link
                :href="route('dashboards.create')"
                :class="
                    cn(
                        buttonVariants(),
                        'hidden rounded-full px-5 shadow-[0_8px_22px_-10px_hsl(var(--primary))] transition-transform duration-300 hover:-translate-y-0.5 sm:inline-flex',
                    )
                "
            >
                <Plus aria-hidden="true" />
                Nueva pregunta
            </Link>
        </div>

        <div v-if="tenants.length" class="w-full border-t py-3 min-[769px]:hidden">
            <TenantSwitcher :current-tenant="currentTenant" :tenants="tenants" />
        </div>
    </header>
</template>
