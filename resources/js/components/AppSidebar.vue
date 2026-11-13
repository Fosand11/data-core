<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavDashboardList from '@/components/NavDashboardList.vue';
import NavMain from '@/components/NavMain.vue';
import StudioCredit from '@/components/StudioCredit.vue';
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
import { LayoutGrid, Plus, Settings, Sparkles } from 'lucide-vue-next';

const page = usePage<SharedData>();
const { state } = useSidebar();

const mainNavItems: NavItem[] = [
    { title: 'Inicio', href: '/dashboard', icon: Sparkles },
    { title: 'Dashboards', href: '/dashboards', icon: LayoutGrid },
    { title: 'Configuración', href: '/settings/profile', icon: Settings },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="insight-sidebar">
        <SidebarHeader class="gap-4 p-3 pt-4">
            <Link
                :href="route('dashboard')"
                class="flex items-center rounded-lg px-1.5 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="InsightFlow, ir al inicio"
            >
                <AppLogo :compact="state === 'collapsed'" />
            </Link>

            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Nueva pregunta"
                        class="h-10 justify-center rounded-xl bg-primary font-medium text-primary-foreground shadow-sm hover:bg-primary-hover hover:text-primary-foreground"
                    >
                        <Link :href="route('dashboards.create')">
                            <Plus class="size-4" aria-hidden="true" />
                            <span>Nueva pregunta</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-5 px-3 py-2">
            <NavMain :items="mainNavItems" />
            <NavDashboardList title="Fijados" :dashboards="page.props.pinnedDashboards ?? []" />
            <NavDashboardList title="Recientes" :dashboards="page.props.recentDashboards ?? []" />
        </SidebarContent>

        <SidebarFooter class="gap-2 p-3">
            <UserMenu :user="page.props.auth.user" :compact="state === 'collapsed'" />
            <StudioCredit v-if="state !== 'collapsed'" class="px-2" />
        </SidebarFooter>
    </Sidebar>
</template>
