<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ChartPaletteOption } from '@/types';
import { ChevronDown, Palette } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    palettes: ChartPaletteOption[];
    /** The id of the palette in use. */
    current: string;
    disabled?: boolean;
}>();

// Bound (not a static class) so it is re-applied together with the attributes Radix sets on the trigger.
const triggerClass =
    'inline-flex h-9 items-center gap-2 rounded-full border border-border/80 bg-card px-3.5 text-sm font-medium text-foreground transition-colors hover:border-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';

const emit = defineEmits<{ select: [id: string] }>();

const active = computed(() => props.palettes.find((palette) => palette.id === props.current) ?? props.palettes[0]);

const onSelect = (value: unknown) => {
    if (typeof value === 'string' && value !== props.current) emit('select', value);
};
</script>

<template>
    <!-- A real root element, so attributes from the parent (such as print:hidden) land here and not on the trigger. -->
    <div v-if="active">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button type="button" :disabled="disabled" :class="triggerClass" aria-label="Cambiar la paleta de colores del dashboard">
                    <Palette class="size-4 text-muted-foreground" aria-hidden="true" />
                    <span class="text-muted-foreground">Colores</span>
                    <span class="flex -space-x-1" aria-hidden="true">
                        <span
                            v-for="color in active.light.slice(0, 3)"
                            :key="color"
                            class="size-3.5 rounded-full ring-2 ring-card"
                            :style="{ backgroundColor: color }"
                        />
                    </span>
                    <span class="hidden sm:inline">{{ active.name }}</span>
                    <ChevronDown class="size-3.5 text-muted-foreground" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-64 rounded-xl">
                <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">Paleta de todo el dashboard</DropdownMenuLabel>
                <DropdownMenuRadioGroup :model-value="current" @update:model-value="onSelect">
                    <DropdownMenuRadioItem v-for="palette in palettes" :key="palette.id" :value="palette.id" class="gap-3 rounded-lg">
                        <span class="flex shrink-0 -space-x-1" aria-hidden="true">
                            <span
                                v-for="color in palette.light"
                                :key="color"
                                class="size-4 rounded-full ring-2 ring-popover"
                                :style="{ backgroundColor: color }"
                            />
                        </span>
                        <span>{{ palette.name }}</span>
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
