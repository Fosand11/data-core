<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
const pagePath = computed(() => page.url.split('?')[0]);

// The longest matching href wins, so "/dashboards/create" does not also light up "/dashboards".
const activeHref = computed(
    () =>
        props.items
            .filter((item) => pagePath.value === item.href || (item.href !== '/dashboard' && pagePath.value.startsWith(`${item.href}/`)))
            .sort((first, second) => second.href.length - first.href.length)
            .at(0)?.href,
);

const isActive = (href: string) => activeHref.value === href;
</script>

<template>
    <SidebarGroup class="p-0">
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item.href)"
                    :tooltip="item.title"
                    class="h-9 rounded-lg px-2.5 text-[0.9rem] font-medium text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:text-foreground"
                >
                    <Link :href="item.href" :aria-current="isActive(item.href) ? 'page' : undefined">
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            :class="['size-[1.1rem]', isActive(item.href) ? 'text-primary' : 'text-muted-foreground']"
                            aria-hidden="true"
                        />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
