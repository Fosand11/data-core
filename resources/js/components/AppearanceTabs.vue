<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

interface Props {
    class?: string;
}

const { class: containerClass = '' } = defineProps<Props>();

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Claro', description: 'Superficies luminosas' },
    { value: 'dark', Icon: Moon, label: 'Oscuro', description: 'Contraste nocturno' },
    { value: 'system', Icon: Monitor, label: 'Sistema', description: 'Sigue tu dispositivo' },
] as const;
</script>

<template>
    <div :class="['grid gap-3 sm:grid-cols-3', containerClass]">
        <button
            v-for="{ value, Icon, label, description } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :aria-pressed="appearance === value"
            :class="[
                'group flex min-h-28 flex-col items-start justify-between rounded-2xl border p-4 text-left transition-all duration-300',
                appearance === value
                    ? 'border-primary/25 bg-primary-subtle text-foreground shadow-card dark:bg-neutral-800'
                    : 'border-border bg-background text-muted-foreground hover:-translate-y-0.5 hover:border-primary/20 hover:bg-card hover:text-foreground',
            ]"
        >
            <span
                :class="[
                    'flex size-9 items-center justify-center rounded-full transition-transform duration-300 group-hover:-rotate-6',
                    appearance === value ? 'bg-primary text-white' : 'bg-muted text-muted-foreground',
                ]"
            >
                <component :is="Icon" class="size-4" aria-hidden="true" />
            </span>
            <span>
                <span class="block text-sm font-semibold">{{ label }}</span>
                <span class="mt-1 block text-xs opacity-70">{{ description }}</span>
            </span>
        </button>
    </div>
</template>
