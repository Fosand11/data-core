<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

const isActive = (href: string) => page.url === href || (href !== '/dashboard' && page.url.startsWith(`${href}/`));
</script>

<template>
    <SidebarGroup class="p-0">
        <SidebarGroupLabel class="sr-only">Navegación principal</SidebarGroupLabel>
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    v-if="item.disabled"
                    class="min-h-10 cursor-not-allowed text-muted-foreground opacity-70"
                    :tooltip="`${item.title}: disponible próximamente`"
                    aria-disabled="true"
                >
                    <component v-if="item.icon" :is="item.icon" aria-hidden="true" />
                    <span>{{ item.title }}</span>
                </SidebarMenuButton>
                <SidebarMenuButton v-else as-child :is-active="isActive(item.href)" :tooltip="item.title">
                    <Link :href="item.href" :aria-current="isActive(item.href) ? 'page' : undefined">
                        <component v-if="item.icon" :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
