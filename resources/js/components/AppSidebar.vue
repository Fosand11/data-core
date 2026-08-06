<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import UserMenu from '@/components/UserMenu.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, CircleHelp, Database, History, Home, Settings, Sparkles, Users } from 'lucide-vue-next';

const page = usePage<SharedData>();
const { state } = useSidebar();

const mainNavItems: NavItem[] = [
    { title: 'Inicio', href: '/dashboard', icon: Home },
    { title: 'Dashboards', href: '#dashboards', icon: BarChart3, disabled: true, badge: 'Próximamente' },
    { title: 'Crear con IA', href: '#create', icon: Sparkles, disabled: true, badge: 'Próximamente' },
    { title: 'Fuentes de datos', href: '#sources', icon: Database, disabled: true },
    { title: 'Historial', href: '#history', icon: History, disabled: true },
    { title: 'Organización', href: '#organization', icon: Users, disabled: true },
    { title: 'Configuración', href: '/settings/profile', icon: Settings },
];

const footerNavItems: NavItem[] = [{ title: 'Centro de ayuda', href: '#help', icon: CircleHelp, disabled: true }];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r bg-card">
        <SidebarHeader class="h-[4.5rem] justify-center border-b px-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-transparent data-[state=open]:bg-transparent">
                        <Link :href="route('dashboard')" aria-label="InsightFlow, ir al inicio">
                            <AppLogo :compact="state === 'collapsed'" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2 py-4">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t p-3">
            <NavFooter :items="footerNavItems" />
            <UserMenu :user="page.props.auth.user" :compact="state === 'collapsed'" />
        </SidebarFooter>
    </Sidebar>
</template>
