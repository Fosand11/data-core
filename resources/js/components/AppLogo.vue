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
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-[0.65rem] bg-primary text-primary-foreground">
        <AppLogoIcon class="size-[1.15rem]" />
    </div>
    <span v-if="!compact" class="ml-2.5 truncate text-[0.95rem] font-semibold tracking-tight text-foreground">InsightFlow</span>
</template>
