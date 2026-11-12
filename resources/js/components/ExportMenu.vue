<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { printDashboard } from '@/composables/usePrint';
import { showToast } from '@/composables/useToast';
import { downloadBlob, exportFilename } from '@/lib/export';
import type { DashboardSpec, ExportContext, VisualizationData } from '@/types';
import { Download, LoaderCircle, Presentation, Printer } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    spec: DashboardSpec;
    data?: VisualizationData | null;
    context: ExportContext;
    /** Nothing is exported while an unapplied proposal is on screen. */
    disabled?: boolean;
}>();

const working = ref<'pdf' | 'pptx' | null>(null);

async function exportPdf() {
    working.value = 'pdf';

    try {
        await printDashboard();
    } catch {
        showToast('No se pudo preparar la impresión. Inténtalo de nuevo.');
    } finally {
        working.value = null;
    }
}

async function exportPowerPoint() {
    if (!props.data) return;

    working.value = 'pptx';

    try {
        // Loaded on demand: the PowerPoint library is large and most visits never export.
        const { buildDeck } = await import('@/lib/exportPptx');
        const blob = await buildDeck(props.spec, props.data, props.context);
        const filename = exportFilename([props.context.title], 'pptx');

        downloadBlob(blob, filename);
        showToast(`Presentación descargada: ${filename}`);
    } catch {
        showToast('No se pudo crear la presentación. Inténtalo de nuevo.');
    } finally {
        working.value = null;
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :disabled="disabled || working !== null || !data"
                class="inline-flex h-9 items-center gap-2 rounded-full border border-border/80 bg-card px-3.5 text-sm font-medium text-foreground transition-colors hover:border-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 print:hidden"
                :title="disabled ? 'Aplica o descarta el cambio propuesto para exportar' : undefined"
                aria-label="Exportar el dashboard"
            >
                <LoaderCircle v-if="working" class="size-4 animate-spin" aria-hidden="true" />
                <Download v-else class="size-4 text-muted-foreground" aria-hidden="true" />
                Exportar
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-60 rounded-xl">
            <DropdownMenuItem class="gap-2.5 rounded-lg" @select="exportPdf">
                <Printer class="size-4 text-muted-foreground" aria-hidden="true" />
                <span class="flex flex-col">PDF<span class="text-xs text-muted-foreground">Imprimir o guardar como PDF</span></span>
            </DropdownMenuItem>
            <DropdownMenuItem class="gap-2.5 rounded-lg" @select="exportPowerPoint">
                <Presentation class="size-4 text-muted-foreground" aria-hidden="true" />
                <span class="flex flex-col">PowerPoint<span class="text-xs text-muted-foreground">Presentación editable (.pptx)</span></span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
