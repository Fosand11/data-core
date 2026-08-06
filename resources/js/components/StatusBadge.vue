<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info';
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'neutral',
    },
);

const variants = {
    neutral: 'bg-muted text-muted-foreground',
    primary: 'bg-primary-subtle text-primary',
    success: 'bg-emerald-50 text-success',
    warning: 'bg-amber-50 text-warning',
    error: 'bg-red-50 text-error',
    info: 'bg-blue-50 text-info',
};
</script>

<template>
    <span :class="cn('inline-flex items-center rounded-md px-2 py-1 text-xs font-medium', variants[props.variant], props.class)">
        <slot />
    </span>
</template>
