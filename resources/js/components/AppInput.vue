<script setup lang="ts">
import { Input } from '@/components/ui/input';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        modelValue?: string | number;
        invalid?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        modelValue: '',
        invalid: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();
</script>

<template>
    <Input
        v-bind="$attrs"
        :model-value="modelValue"
        :class="$props.class"
        :aria-invalid="invalid"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
