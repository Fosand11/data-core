<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string | number;
    icon?: Component;
    unavailable?: boolean;
}>();
</script>

<template>
    <article class="rounded-xl border bg-card p-5 shadow-card">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">{{ label }}</p>
                <p :class="['mt-4 font-semibold text-foreground', unavailable ? 'text-base' : 'text-3xl']">
                    {{ value }}
                </p>
            </div>
            <div v-if="icon" class="flex size-9 items-center justify-center rounded-lg bg-primary-subtle text-primary">
                <component :is="icon" class="size-5" aria-hidden="true" />
            </div>
        </div>
    </article>
</template>
