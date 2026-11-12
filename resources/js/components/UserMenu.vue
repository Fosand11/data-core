<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { ChevronsUpDown } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        user: User;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :class="[
                    'flex w-full items-center rounded-xl text-left transition-colors hover:bg-sidebar-accent/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                    compact ? 'justify-center p-1' : 'gap-2.5 p-2',
                ]"
                aria-label="Abrir menú de usuario"
            >
                <UserInfo :user="user" :compact="compact" />
                <ChevronsUpDown v-if="!compact" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 rounded-xl" side="top" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
