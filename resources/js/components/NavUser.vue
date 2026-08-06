<script setup lang="ts">
import UserMenu from '@/components/UserMenu.vue';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <SidebarMenuButton size="lg" as-child>
                <UserMenu :user="user" />
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
