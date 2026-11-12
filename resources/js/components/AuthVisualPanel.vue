<script setup lang="ts">
import ProductPreview from '@/components/ProductPreview.vue';

withDefaults(
    defineProps<{
        title?: string;
    }>(),
    {
        title: 'De una pregunta a un dashboard',
    },
);
</script>

<template>
    <!-- Always rendered with the dark tokens, whichever theme the visitor uses. -->
    <aside class="dark relative hidden min-h-svh overflow-hidden bg-background px-10 py-12 text-foreground lg:flex lg:flex-col" aria-hidden="true">
        <div class="relative mx-auto mt-10 w-full max-w-2xl">
            <ProductPreview />
        </div>

        <div class="relative mx-auto mt-auto max-w-xl pb-6 pt-12 text-center">
            <h2 class="text-3xl font-semibold leading-tight tracking-tight">{{ title }}</h2>
            <p class="mx-auto mt-4 max-w-md text-base leading-7 text-muted-foreground">
                Escribe lo que quieres entender. Diseñamos el dashboard y calculamos los números con tus datos.
            </p>
        </div>
    </aside>
</template>
