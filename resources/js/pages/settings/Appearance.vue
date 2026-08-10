<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Apariencia',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Apariencia" />

        <SettingsLayout>
            <section class="insight-panel p-6 sm:p-8" aria-labelledby="appearance-title">
                <div class="border-b border-border/70 pb-5">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-primary">Atmósfera visual</p>
                    <h2 id="appearance-title" class="mt-2 text-2xl font-semibold tracking-tight text-foreground">Elige cómo quieres mirar</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        La preferencia se guarda en este navegador y puede seguir la configuración del sistema.
                    </p>
                </div>
                <div class="mt-6">
                    <AppearanceTabs />
                </div>
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
