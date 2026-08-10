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
    success: 'bg-emerald-50 text-success dark:bg-success/15 dark:text-emerald-300',
    warning: 'bg-amber-50 text-warning dark:bg-warning/15 dark:text-amber-300',
    error: 'bg-red-50 text-error dark:bg-error/15 dark:text-red-300',
    info: 'bg-blue-50 text-info dark:bg-info/15 dark:text-blue-300',
};
</script>

<template>
    <span :class="cn('inline-flex items-center rounded-md px-2 py-1 text-xs font-medium', variants[props.variant], props.class)">
        <slot />
    </span>
</template>
