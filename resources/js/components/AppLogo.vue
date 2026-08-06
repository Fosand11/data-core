<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});
</script>

<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-white shadow-sm">
        <AppLogoIcon class="size-5" />
    </div>
    <div v-if="!compact" class="ml-1 grid flex-1 text-left">
        <span class="truncate text-base font-bold leading-none text-foreground">InsightFlow</span>
    </div>
</template>
