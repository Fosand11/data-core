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
    <article
        class="group relative overflow-hidden rounded-[1.25rem] border bg-card p-5 shadow-card transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/20"
    >
        <span
            class="absolute inset-y-5 left-0 w-0.5 rounded-r-full bg-primary/35 transition-all duration-300 group-hover:bg-primary"
            aria-hidden="true"
        />
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ label }}</p>
                <p :class="['mt-4 font-semibold text-foreground', unavailable ? 'text-base' : 'text-3xl']">
                    {{ value }}
                </p>
            </div>
            <div
                v-if="icon"
                class="flex size-9 items-center justify-center rounded-full border border-primary/10 bg-primary-subtle text-primary transition-transform duration-300 group-hover:-rotate-6"
            >
                <component :is="icon" class="size-5" aria-hidden="true" />
            </div>
        </div>
    </article>
</template>
