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
    <main class="mx-auto w-full max-w-[1380px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="motion-reveal border-b border-border/70 pb-7">
            <p class="editorial-kicker">Tu espacio personal</p>
            <h1 class="mt-4 text-4xl font-bold tracking-[-0.045em] text-foreground sm:text-5xl">Ajustes con intención.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
                Configura cómo te identificas, cómo accedes y cómo prefieres ver InsightFlow.
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
                                'flex size-10 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:-rotate-3',
                                currentPath === item.href ? 'bg-primary text-white' : 'bg-muted text-muted-foreground',
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
