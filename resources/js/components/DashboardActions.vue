<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EditDashboardDialog from '@/components/EditDashboardDialog.vue';
import { buttonVariants } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { DashboardDetail, DashboardVersionEntry } from '@/types';
import { router } from '@inertiajs/vue3';
import { Copy, Ellipsis, Pencil, Pin, PinOff, RotateCcw, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    dashboard: DashboardDetail;
    /** Newest first; the first entry is the current version. */
    versions: DashboardVersionEntry[];
}>();

const editing = ref(false);
const confirmingDelete = ref(false);
const busy = ref(false);

const canRetry = computed(() => props.dashboard.can.retry);
const canDuplicate = computed(() => props.dashboard.can.duplicate && props.dashboard.status === 'ready');
// "Undo" restores the version before the current one; "original" only differs when there is more history.
const canUndo = computed(() => props.dashboard.can.refine && props.versions.length >= 2);
const previousNumber = computed(() => props.versions[1]?.number);
const originalNumber = computed(() => props.versions.at(-1)?.number);
const canRestoreOriginal = computed(() => canUndo.value && props.versions.length >= 3);
const hasMenu = computed(() => props.dashboard.can.update || canDuplicate.value || props.dashboard.can.delete || canUndo.value);

const finish = { preserveScroll: true, onFinish: () => (busy.value = false) };

const togglePin = () => {
    busy.value = true;
    router.patch(route('dashboards.pin', { dashboard: props.dashboard.id }), { pinned: !props.dashboard.is_pinned }, finish);
};

const retry = () => {
    busy.value = true;
    router.post(route('dashboards.retry', { dashboard: props.dashboard.id }), {}, finish);
};

const duplicate = () => {
    busy.value = true;
    router.post(route('dashboards.duplicate', { dashboard: props.dashboard.id }), {}, { onFinish: () => (busy.value = false) });
};

const restore = (number: number | undefined) => {
    if (number === undefined) return;

    busy.value = true;
    router.post(route('dashboards.versions.restore', { dashboard: props.dashboard.id, number }), {}, finish);
};

const destroy = () => {
    busy.value = true;
    router.delete(route('dashboards.destroy', { dashboard: props.dashboard.id }), {
        onFinish: () => {
            busy.value = false;
            confirmingDelete.value = false;
        },
    });
};

const iconButton =
    'flex size-9 items-center justify-center rounded-full border border-border/80 bg-card text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50';
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            v-if="canRetry"
            type="button"
            :disabled="busy"
            :class="cn(buttonVariants({ variant: 'outline' }), 'rounded-full px-4')"
            @click="retry"
        >
            <RotateCcw aria-hidden="true" />
            Reintentar
        </button>

        <button
            type="button"
            :class="iconButton"
            :disabled="busy"
            :aria-pressed="dashboard.is_pinned"
            :aria-label="dashboard.is_pinned ? 'Quitar de fijados' : 'Fijar en la barra lateral'"
            :title="dashboard.is_pinned ? 'Quitar de fijados' : 'Fijar en la barra lateral'"
            @click="togglePin"
        >
            <PinOff v-if="dashboard.is_pinned" class="size-4 text-primary" aria-hidden="true" />
            <Pin v-else class="size-4" aria-hidden="true" />
        </button>

        <DropdownMenu v-if="hasMenu">
            <DropdownMenuTrigger as-child>
                <button type="button" :class="iconButton" aria-label="Más acciones">
                    <Ellipsis class="size-4" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-52 rounded-xl">
                <DropdownMenuItem v-if="dashboard.can.update" class="gap-2.5 rounded-lg" @select="editing = true">
                    <Pencil class="size-4 text-muted-foreground" aria-hidden="true" /> Editar detalles
                </DropdownMenuItem>
                <DropdownMenuItem v-if="canDuplicate" class="gap-2.5 rounded-lg" :disabled="busy" @select="duplicate">
                    <Copy class="size-4 text-muted-foreground" aria-hidden="true" /> Duplicar
                </DropdownMenuItem>
                <DropdownMenuItem v-if="canUndo" class="gap-2.5 rounded-lg" :disabled="busy" @select="restore(previousNumber)">
                    <Undo2 class="size-4 text-muted-foreground" aria-hidden="true" /> Deshacer último cambio
                </DropdownMenuItem>
                <DropdownMenuItem v-if="canRestoreOriginal" class="gap-2.5 rounded-lg" :disabled="busy" @select="restore(originalNumber)">
                    <RotateCcw class="size-4 text-muted-foreground" aria-hidden="true" /> Volver al original
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="dashboard.can.delete && (dashboard.can.update || canDuplicate || canUndo)" />
                <DropdownMenuItem
                    v-if="dashboard.can.delete"
                    class="gap-2.5 rounded-lg text-error focus:text-error"
                    @select="confirmingDelete = true"
                >
                    <Trash2 class="size-4" aria-hidden="true" /> Eliminar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <EditDashboardDialog v-model:open="editing" :dashboard-id="dashboard.id" :title="dashboard.title" :description="dashboard.description" />
        <ConfirmDialog
            v-model:open="confirmingDelete"
            title="¿Eliminar este dashboard?"
            :description="`«${dashboard.title}» se eliminará de forma permanente para toda la organización. Esta acción no se puede deshacer.`"
            confirm-label="Eliminar"
            destructive
            :loading="busy"
            @confirm="destroy"
        />
    </div>
</template>
