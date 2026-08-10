<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <div class="flex min-w-0 items-center gap-2 min-[769px]:hidden">
        <SidebarTrigger class="size-11 shrink-0 rounded-full border bg-card shadow-card" aria-label="Abrir navegación" />
        <AppLogo compact />
    </div>
</template>
