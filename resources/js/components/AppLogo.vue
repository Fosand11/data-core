<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
    compact?: boolean;
    inverse?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
    inverse: false,
});
</script>

<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-[0.7rem] bg-primary text-white shadow-sm">
        <AppLogoIcon class="size-5" />
    </div>
    <div v-if="!compact" class="ml-1 grid flex-1 text-left">
        <span :class="['truncate text-base font-bold leading-none', inverse ? 'text-white' : 'text-foreground']">InsightFlow</span>
        <span
            :class="[
                'mt-1 truncate text-[0.6rem] font-semibold uppercase tracking-[0.18em]',
                inverse ? 'text-indigo-200/70' : 'text-muted-foreground',
            ]"
        >
            Analysis studio
        </span>
    </div>
</template>
