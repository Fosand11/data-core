<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon?: Component;
}>();
</script>

<template>
    <section class="mx-auto flex w-full max-w-3xl flex-col items-center text-center" aria-labelledby="empty-state-title">
        <div v-if="icon" class="flex size-20 items-center justify-center rounded-full bg-primary-subtle text-primary">
            <component :is="icon" class="size-9" aria-hidden="true" />
        </div>
        <h1 id="empty-state-title" class="mt-6 text-2xl font-bold tracking-tight text-foreground sm:text-h2">
            {{ title }}
        </h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
            {{ description }}
        </p>
        <div class="mt-8 w-full">
            <slot />
        </div>
    </section>
</template>
