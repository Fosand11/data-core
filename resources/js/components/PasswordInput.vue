<script setup lang="ts">
import AppInput from '@/components/AppInput.vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        modelValue: string;
        invalid?: boolean;
    }>(),
    {
        invalid: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <AppInput
            v-bind="$attrs"
            :model-value="modelValue"
            :invalid="invalid"
            :type="visible ? 'text' : 'password'"
            class="pr-12"
            @update:model-value="emit('update:modelValue', String($event))"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
            :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="size-4.5" aria-hidden="true" />
            <Eye v-else class="size-4.5" aria-hidden="true" />
        </button>
    </div>
</template>
