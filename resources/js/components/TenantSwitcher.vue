<script setup lang="ts">
import type { CurrentTenant, TenantOption } from '@/types';
import { router } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    currentTenant: CurrentTenant | null;
    tenants: TenantOption[];
}>();

const selecting = ref(false);
const selectedSlug = computed(() => props.currentTenant?.slug ?? '');

function selectTenant(event: Event): void {
    const slug = (event.target as HTMLSelectElement).value;

    if (!slug || slug === props.currentTenant?.slug) {
        return;
    }

    selecting.value = true;
    router.patch(
        route('tenants.select', { tenant: slug }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                selecting.value = false;
            },
        },
    );
}
</script>

<template>
    <label v-if="tenants.length" class="group relative flex w-full min-w-0 items-center min-[769px]:w-auto">
        <span class="sr-only">Organización actual</span>
        <span
            class="pointer-events-none absolute left-2.5 flex size-7 items-center justify-center rounded-full bg-primary-subtle text-[0.65rem] font-bold uppercase text-primary"
        >
            {{ currentTenant?.name.slice(0, 1) ?? '—' }}
        </span>
        <select
            :value="selectedSlug"
            :disabled="selecting"
            class="h-11 w-full min-w-0 appearance-none rounded-full border border-border/80 bg-card/75 py-2 pl-11 pr-9 text-sm font-semibold text-foreground shadow-sm transition-all hover:border-primary/30 hover:bg-card focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15 min-[769px]:w-56"
            aria-label="Cambiar organización"
            @change="selectTenant"
        >
            <option v-for="tenant in tenants" :key="tenant.slug" :value="tenant.slug">
                {{ tenant.name }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute right-3 size-4 text-muted-foreground transition-transform duration-300 group-focus-within:rotate-180"
            aria-hidden="true"
        />
    </label>
</template>
