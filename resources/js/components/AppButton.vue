<script setup lang="ts">
import { Button, type ButtonVariants } from '@/components/ui/button';
import { LoaderCircle } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        variant?: ButtonVariants['variant'];
        size?: ButtonVariants['size'];
        loading?: boolean;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'default',
        size: 'default',
        loading: false,
        disabled: false,
    },
);
</script>

<template>
    <Button v-bind="$attrs" :variant="variant" :size="size" :class="$props.class" :disabled="disabled || loading" :aria-busy="loading">
        <LoaderCircle v-if="loading" class="animate-spin" aria-hidden="true" />
        <slot />
    </Button>
</template>
