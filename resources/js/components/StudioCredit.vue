<script setup lang="ts">
import { STUDIO } from '@/lib/brand';
</script>

<template>
    <a
        :href="STUDIO.url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="`Un proyecto de ${STUDIO.name} (se abre en una pestaña nueva)`"
    >
        by <span class="font-semibold tracking-wide">{{ STUDIO.name }}</span>
    </a>
</template>
