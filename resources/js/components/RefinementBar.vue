<script setup lang="ts">
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { RefinementState } from '@/types';
import { LoaderCircle, TriangleAlert, WandSparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    refinement: RefinementState;
    busy?: boolean;
}>();

const viewing = defineModel<'proposal' | 'current'>('viewing', { required: true });
const emit = defineEmits<{ apply: []; discard: [] }>();

const running = computed(() => props.refinement.status === 'queued' || props.refinement.status === 'processing');
const failed = computed(() => props.refinement.status === 'failed');
const options = [
    { value: 'proposal', label: 'Propuesta' },
    { value: 'current', label: 'Actual' },
] as const;
</script>

<template>
    <aside
        class="sticky top-[3.75rem] z-20 rounded-2xl border bg-card/95 p-3 shadow-soft backdrop-blur-xl sm:p-4"
        :class="failed ? 'border-error/30' : 'border-primary/30'"
        aria-label="Cambio propuesto"
        aria-live="polite"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                :class="failed ? 'bg-error/10 text-error' : 'bg-primary-subtle text-primary'"
            >
                <TriangleAlert v-if="failed" class="size-[1.1rem]" aria-hidden="true" />
                <LoaderCircle v-else-if="running" class="size-[1.1rem] animate-spin" aria-hidden="true" />
                <WandSparkles v-else class="size-[1.1rem]" aria-hidden="true" />
            </span>

            <div class="min-w-0 flex-1 basis-56">
                <p class="text-sm font-medium text-foreground">
                    <template v-if="running">Preparando el cambio…</template>
                    <template v-else-if="failed">No se pudo preparar el cambio</template>
                    <template v-else-if="refinement.is_stale">Esta propuesta ya no está al día</template>
                    <template v-else>Vista previa del cambio</template>
                </p>
                <p class="truncate text-sm text-muted-foreground" :title="refinement.instruction">«{{ refinement.instruction }}»</p>
                <p v-if="failed" class="mt-1 text-sm text-foreground/80">{{ refinement.error_message }}</p>
                <p v-else-if="refinement.is_stale" class="mt-1 text-sm text-foreground/80">
                    El dashboard cambió después de esta propuesta. Descártala y pide el ajuste de nuevo.
                </p>
            </div>

            <div
                v-if="!running && !failed && !refinement.is_stale"
                class="flex rounded-full border bg-muted/60 p-0.5"
                role="group"
                aria-label="Qué versión ver"
            >
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    :aria-pressed="viewing === option.value"
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        viewing === option.value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="viewing = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    :disabled="busy"
                    :class="cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'rounded-full px-4')"
                    @click="emit('discard')"
                >
                    {{ running ? 'Cancelar' : 'Descartar' }}
                </button>
                <button
                    v-if="!running && !failed && !refinement.is_stale"
                    type="button"
                    :disabled="busy"
                    :class="cn(buttonVariants({ size: 'sm' }), 'rounded-full px-4')"
                    @click="emit('apply')"
                >
                    <LoaderCircle v-if="busy" class="animate-spin" aria-hidden="true" />
                    Aplicar cambios
                </button>
            </div>
        </div>
    </aside>
</template>
