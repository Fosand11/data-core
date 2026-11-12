<script setup lang="ts">
import MobileNavigation from '@/components/MobileNavigation.vue';
import TenantSwitcher from '@/components/TenantSwitcher.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';
import type { BreadcrumbItem, CurrentTenant, TenantOption } from '@/types';
import { Search } from 'lucide-vue-next';

// Breadcrumbs stay in the contract (pages still pass them) but the header no longer renders a title:
// every page owns its heading.
withDefaults(
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

const { show } = useCommandPalette();
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border/60 bg-background/75 px-4 backdrop-blur-xl backdrop-saturate-150 sm:px-6 print:hidden"
    >
        <MobileNavigation />
        <TenantSwitcher v-if="tenants.length" :current-tenant="currentTenant" :tenants="tenants" />

        <button
            type="button"
            class="ml-auto flex h-9 items-center gap-2 rounded-full border border-border/80 bg-card/70 px-3.5 text-sm text-muted-foreground transition-colors hover:bg-card hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-64 sm:justify-start"
            aria-label="Buscar (abrir paleta de comandos)"
            aria-haspopup="dialog"
            @click="show"
        >
            <Search class="size-4 shrink-0" aria-hidden="true" />
            <span class="hidden flex-1 text-left sm:inline">Buscar…</span>
            <kbd class="hidden rounded-md border bg-muted px-1.5 py-0.5 text-[0.65rem] font-medium sm:inline">{{ isMac ? '⌘' : 'Ctrl' }} K</kbd>
        </button>
    </header>
</template>
