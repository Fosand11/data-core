<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatRange } from '@/lib/format';
import type { DashboardPeriodInfo } from '@/types';
import { CalendarRange, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    period: DashboardPeriodInfo;
    busy?: boolean;
}>();

const emit = defineEmits<{ select: [value: string] }>();

const caption = computed(() => (props.period.range ? formatRange(props.period.range.from, props.period.range.to) : null));
// "Según el dashboard" first, then the presets, with a separator before the first preset.
const specOption = computed(() => props.period.options.find((option) => option.value === 'spec'));
const presets = computed(() => props.period.options.filter((option) => option.value !== 'spec'));

const onSelect = (value: unknown) => {
    if (typeof value === 'string' && value !== props.period.value) emit('select', value);
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-full border border-border/80 bg-card px-3.5 text-sm font-medium text-foreground transition-colors hover:border-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-busy="busy"
                    aria-label="Cambiar el periodo del dashboard"
                >
                    <CalendarRange class="size-4 text-muted-foreground" aria-hidden="true" />
                    <span class="text-muted-foreground">Periodo</span>
                    <span>{{ period.label }}</span>
                    <ChevronDown class="size-3.5 text-muted-foreground" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56 rounded-xl">
                <DropdownMenuRadioGroup :model-value="period.value" @update:model-value="onSelect">
                    <DropdownMenuRadioItem v-if="specOption" :value="specOption.value" class="rounded-lg">{{
                        specOption.label
                    }}</DropdownMenuRadioItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioItem v-for="option in presets" :key="option.value" :value="option.value" class="rounded-lg">
                        {{ option.label }}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>

        <span v-if="caption" class="text-sm text-muted-foreground">{{ caption }}</span>
    </div>
</template>
