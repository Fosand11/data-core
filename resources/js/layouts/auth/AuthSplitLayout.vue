<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AuthVisualPanel from '@/components/AuthVisualPanel.vue';
import { appName } from '@/lib/brand';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
    promoTitle?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh bg-card lg:grid-cols-[minmax(430px,40%)_minmax(0,60%)]">
        <main class="flex min-h-svh flex-col px-6 py-7 sm:px-10 lg:px-12 lg:py-10 xl:px-16">
            <Link :href="route('home')" class="inline-flex w-fit items-center gap-2 focus-visible:rounded-lg" aria-label="Ir al inicio">
                <AppLogo />
            </Link>

            <div class="mx-auto flex w-full max-w-[472px] flex-1 flex-col justify-center py-12">
                <div class="mb-8 space-y-2">
                    <h1 v-if="title" class="text-3xl font-normal tracking-tight text-foreground sm:text-h1">{{ title }}</h1>
                    <p v-if="description" class="text-sm leading-6 text-muted-foreground sm:text-base">{{ description }}</p>
                </div>
                <slot />
            </div>

            <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground lg:justify-start">
                <span>© {{ new Date().getFullYear() }} {{ appName() }}</span>
                <a :href="route('privacy')" target="_blank" rel="noopener" class="hover:text-foreground hover:underline">Política de privacidad</a>
            </p>
        </main>

        <AuthVisualPanel :title="promoTitle" />
    </div>
</template>
