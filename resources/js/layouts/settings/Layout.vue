<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Fingerprint, Palette, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';

const sidebarNavItems = [
    {
        title: 'Identidad',
        description: 'Nombre y correo',
        href: '/settings/profile',
        icon: UserRound,
    },
    {
        title: 'Acceso',
        description: 'Contraseña y seguridad',
        href: '/settings/password',
        icon: Fingerprint,
    },
    {
        title: 'Apariencia',
        description: 'Preferencia visual',
        href: '/settings/appearance',
        icon: Palette,
    },
];

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
</script>

<template>
    <main class="mx-auto w-full max-w-5xl px-4 pb-20 pt-10 sm:px-6 sm:pt-14">
        <header class="motion-reveal">
            <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Configuración</h1>
            <p class="mt-2 max-w-2xl text-base leading-7 text-muted-foreground">
                Cómo te identificas, cómo accedes y cómo prefieres ver InsightFlow.
            </p>
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-12">
            <aside class="motion-reveal-delayed">
                <nav class="grid gap-2 sm:grid-cols-3 lg:sticky lg:top-24 lg:grid-cols-1" aria-label="Configuración de cuenta">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        :href="item.href"
                        :aria-current="currentPath === item.href ? 'page' : undefined"
                        :class="[
                            'group flex min-h-[4.5rem] items-center gap-3 rounded-2xl border px-3 py-2.5 transition-all duration-300',
                            currentPath === item.href
                                ? 'border-primary/20 bg-primary-subtle text-foreground shadow-card'
                                : 'border-transparent text-muted-foreground hover:border-border hover:bg-card hover:text-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                currentPath === item.href ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                            ]"
                        >
                            <component :is="item.icon" class="size-4.5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 text-sm font-semibold">{{ item.title }}</span>
                            <span class="mt-0.5 block truncate text-xs opacity-70">{{ item.description }}</span>
                        </span>
                    </Link>
                </nav>
            </aside>

            <div class="motion-reveal-late min-w-0">
                <section class="max-w-3xl space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </main>
</template>
