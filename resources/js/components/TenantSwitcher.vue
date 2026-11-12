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
    <label v-if="tenants.length" class="group relative flex min-w-0 items-center">
        <span class="sr-only">Organización actual</span>
        <span
            class="pointer-events-none absolute left-2 flex size-6 items-center justify-center rounded-md bg-primary-subtle text-[0.65rem] font-semibold uppercase text-primary"
            aria-hidden="true"
        >
            {{ currentTenant?.name.slice(0, 1) ?? '—' }}
        </span>
        <select
            :value="selectedSlug"
            :disabled="selecting"
            class="h-9 w-full min-w-0 max-w-[13rem] cursor-pointer appearance-none rounded-lg border-0 bg-transparent py-1 pl-10 pr-8 text-sm font-medium text-foreground transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="Cambiar organización"
            @change="selectTenant"
        >
            <option v-for="tenant in tenants" :key="tenant.slug" :value="tenant.slug">
                {{ tenant.name }}
            </option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-2 size-3.5 text-muted-foreground" aria-hidden="true" />
    </label>
</template>
