<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { ChartPaletteOption } from '@/types';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    palette: ChartPaletteOption;
    /** The slot (0-5) of the palette this chart uses. */
    selected: number;
}>();

// Bound (not a static class) so it is re-applied together with the attributes Radix sets on the trigger.
const triggerClass =
    'flex size-7 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring print:hidden';

const emit = defineEmits<{ select: [slot: number] }>();

// The menu shows the light colours; the chart itself follows the theme in use.
const current = computed(() => props.palette.light[props.selected] ?? props.palette.light[0]);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button type="button" :class="triggerClass" :aria-label="`Cambiar el color de ${title}`">
                <span class="size-3.5 rounded-full ring-1 ring-border" :style="{ backgroundColor: current }" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-auto rounded-xl p-3">
            <DropdownMenuLabel class="px-0 pb-2 pt-0 text-xs font-normal text-muted-foreground">Color de esta gráfica</DropdownMenuLabel>
            <div class="flex gap-2" role="radiogroup" :aria-label="`Color de ${title}`">
                <button
                    v-for="(color, index) in palette.light"
                    :key="color"
                    type="button"
                    role="radio"
                    :aria-checked="index === selected"
                    :aria-label="`Color ${index + 1}`"
                    class="flex size-7 items-center justify-center rounded-full ring-offset-2 ring-offset-popover transition-shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="index === selected ? 'ring-2 ring-foreground' : 'hover:ring-2 hover:ring-border'"
                    :style="{ backgroundColor: color }"
                    @click="emit('select', index)"
                >
                    <Check v-if="index === selected" class="size-3.5 text-white drop-shadow" aria-hidden="true" />
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
