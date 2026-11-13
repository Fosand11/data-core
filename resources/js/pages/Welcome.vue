<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import ProductPreview from '@/components/ProductPreview.vue';
import StudioCredit from '@/components/StudioCredit.vue';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, MessageSquareText, ShieldCheck } from 'lucide-vue-next';

const page = usePage<SharedData>();

const features = [
    {
        icon: MessageSquareText,
        title: 'Habla, no configures',
        text: 'Describe lo que necesitas en español. Sin SQL, sin arrastrar campos, sin plantillas.',
    },
    {
        icon: ShieldCheck,
        title: 'Seguro por diseño',
        text: 'La IA solo propone una estructura: nunca escribe SQL ni ve tus filas. Cada propuesta se valida antes de mostrarse.',
    },
    {
        icon: Building2,
        title: 'Tus datos, aislados',
        text: 'Cada organización ve únicamente sus propios datos, y los números se calculan en tu servidor.',
    },
];
</script>

<template>
    <Head title="Pregúntale a tus datos" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6">
            <Link :href="route('home')" class="flex items-center" aria-label="InsightFlow">
                <AppLogo />
            </Link>
            <nav class="flex items-center gap-2" aria-label="Cuenta">
                <Link v-if="page.props.auth.user" :href="route('dashboard')" :class="cn(buttonVariants(), 'rounded-full px-5')">Ir al panel</Link>
                <template v-else>
                    <Link :href="route('login')" :class="cn(buttonVariants({ variant: 'ghost' }), 'rounded-full px-4')">Iniciar sesión</Link>
                    <Link :href="route('register')" :class="cn(buttonVariants(), 'rounded-full px-5')">Crear cuenta</Link>
                </template>
            </nav>
        </header>

        <main>
            <section class="mx-auto max-w-4xl px-6 pb-12 pt-16 text-center sm:pt-24">
                <p class="text-sm font-medium text-primary">Dashboards con lenguaje natural</p>
                <h1 class="mt-4 text-5xl font-semibold tracking-tight sm:text-7xl">Pregúntale a tus datos.</h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-muted-foreground">
                    Escribe lo que quieres entender. InsightFlow diseña el dashboard, lo valida y calcula los números en tu propio servidor.
                </p>
                <div class="mt-9 flex flex-wrap justify-center gap-3">
                    <Link
                        :href="page.props.auth.user ? route('dashboard') : route('register')"
                        :class="cn(buttonVariants({ size: 'lg' }), 'rounded-full px-7')"
                    >
                        {{ page.props.auth.user ? 'Ir al panel' : 'Empezar gratis' }}
                    </Link>
                    <Link
                        v-if="!page.props.auth.user"
                        :href="route('login')"
                        :class="cn(buttonVariants({ variant: 'outline', size: 'lg' }), 'rounded-full px-7')"
                    >
                        Iniciar sesión
                    </Link>
                </div>
            </section>

            <section class="mx-auto max-w-5xl px-6 pb-20">
                <ProductPreview />
            </section>

            <section class="mx-auto grid max-w-5xl gap-10 px-6 pb-24 sm:grid-cols-3" aria-label="Por qué InsightFlow">
                <div v-for="feature in features" :key="feature.title">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-primary-subtle text-primary">
                        <component :is="feature.icon" class="size-5" aria-hidden="true" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold tracking-tight">{{ feature.title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ feature.text }}</p>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-6 py-6 text-xs text-muted-foreground">
                <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>© {{ new Date().getFullYear() }} InsightFlow</span>
                    <StudioCredit />
                </span>
                <span>Laravel · Vue · ECharts · Gemini</span>
            </div>
        </footer>
    </div>
</template>
