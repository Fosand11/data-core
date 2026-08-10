<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { DashboardStatus } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    status: DashboardStatus;
}>();

const statusPresentation = computed(() => {
    const presentations = {
        queued: { label: 'En cola', variant: 'warning' as const },
        generating: { label: 'Generando', variant: 'info' as const },
        ready: { label: 'Listo', variant: 'success' as const },
        failed: { label: 'Fallido', variant: 'error' as const },
        archived: { label: 'Archivado', variant: 'neutral' as const },
    };

    return presentations[props.status];
});
</script>

<template>
    <StatusBadge :variant="statusPresentation.variant">{{ statusPresentation.label }}</StatusBadge>
</template>
