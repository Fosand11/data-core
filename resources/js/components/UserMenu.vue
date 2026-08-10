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
                class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-white/[0.07] bg-white/[0.035] p-2 text-left text-indigo-50 transition-colors hover:bg-white/[0.07] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                aria-label="Abrir menú de usuario"
            >
                <UserInfo :user="user" :compact="compact" />
                <ChevronsUpDown v-if="!compact" class="ml-auto size-4 shrink-0 text-indigo-200/50" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 rounded-xl" side="top" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
