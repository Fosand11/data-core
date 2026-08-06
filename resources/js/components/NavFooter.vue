<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        v-if="item.disabled"
                        class="min-h-10 cursor-not-allowed text-muted-foreground opacity-70"
                        :tooltip="`${item.title}: disponible próximamente`"
                        aria-disabled="true"
                    >
                        <component :is="item.icon" aria-hidden="true" />
                        <span>{{ item.title }}</span>
                    </SidebarMenuButton>
                    <SidebarMenuButton v-else class="text-muted-foreground hover:text-foreground" as-child :tooltip="item.title">
                        <a :href="item.href" target="_blank" rel="noopener noreferrer">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
