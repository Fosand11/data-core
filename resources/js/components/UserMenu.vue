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
                class="flex min-h-12 w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-sidebar-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                aria-label="Abrir menú de usuario"
            >
                <UserInfo :user="user" :compact="compact" />
                <ChevronsUpDown v-if="!compact" class="ml-auto size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 rounded-xl" side="top" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
