<script setup lang="ts">
import { Label } from '@/components/ui/label';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Label v-bind="$attrs" :class="['text-sm font-medium text-foreground', $props.class]">
        <slot />
    </Label>
</template>
