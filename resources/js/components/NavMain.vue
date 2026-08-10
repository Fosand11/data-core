<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
const pagePath = computed(() => page.url.split('?')[0]);
const activeHref = computed(
    () =>
        props.items
            .filter((item) => !item.disabled)
            .filter((item) => pagePath.value === item.href || (item.href !== '/dashboard' && pagePath.value.startsWith(`${item.href}/`)))
            .sort((first, second) => second.href.length - first.href.length)
            .at(0)?.href,
);

const isActive = (href: string) => activeHref.value === href;
</script>

<template>
    <SidebarGroup class="p-0">
        <SidebarGroupLabel
            class="mb-2 px-3 text-[0.625rem] font-bold uppercase tracking-[0.2em] text-indigo-200/50 group-data-[collapsible=icon]:sr-only"
        >
            Espacio de trabajo
        </SidebarGroupLabel>
        <SidebarMenu class="gap-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title" class="relative">
                <SidebarMenuButton
                    v-if="item.disabled"
                    class="min-h-11 cursor-not-allowed rounded-xl text-indigo-200/45 opacity-70"
                    :tooltip="`${item.title}: disponible próximamente`"
                    aria-disabled="true"
                >
                    <component v-if="item.icon" :is="item.icon" aria-hidden="true" />
                    <span>{{ item.title }}</span>
                </SidebarMenuButton>
                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isActive(item.href)"
                    :tooltip="item.title"
                    class="min-h-11 rounded-xl text-indigo-100/70 transition-all duration-300 hover:translate-x-0.5 hover:bg-white/[0.06] hover:text-white data-[active=true]:bg-indigo-400/15 data-[active=true]:text-white data-[active=true]:shadow-[inset_0_0_0_1px_rgb(165_180_252/0.13)]"
                >
                    <Link :href="item.href" :aria-current="isActive(item.href) ? 'page' : undefined">
                        <span
                            :class="[
                                'flex size-7 shrink-0 items-center justify-center rounded-lg transition-all duration-300',
                                isActive(item.href)
                                    ? 'bg-indigo-300 text-indigo-950 shadow-[0_5px_15px_-5px_rgb(165_180_252/0.8)]'
                                    : 'text-indigo-200/70',
                            ]"
                        >
                            <component v-if="item.icon" :is="item.icon" class="size-4" aria-hidden="true" />
                        </span>
                        <span>{{ item.title }}</span>
                        <span
                            v-if="isActive(item.href)"
                            class="ml-auto size-1 rounded-full bg-indigo-300 group-data-[collapsible=icon]:hidden"
                            aria-hidden="true"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
