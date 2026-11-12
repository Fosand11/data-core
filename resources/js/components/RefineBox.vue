<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowUp, LoaderCircle, WandSparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    dashboardId: string;
    /** A proposal is already running or waiting for a decision. */
    locked?: boolean;
}>();

const suggestions = ['Cambia las barras por un gráfico circular', 'Agrega una tabla de detalle', 'Quita los filtros'];

const form = useForm({ instruction: '' });
const canSubmit = computed(() => !props.locked && !form.processing && form.instruction.trim().length >= 5);

const submit = () => {
    if (!canSubmit.value) return;

    form.post(route('dashboards.refinements.store', { dashboard: props.dashboardId }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <section aria-label="Ajustar este dashboard">
        <form
            class="flex items-center gap-2 rounded-full border border-border/80 bg-card py-1.5 pl-4 pr-1.5 shadow-card focus-within:border-primary/40"
            :class="locked ? 'opacity-60' : ''"
            @submit.prevent="submit"
        >
            <WandSparkles class="size-4 shrink-0 text-primary" aria-hidden="true" />
            <label for="refine-instruction" class="sr-only">Describe el cambio que quieres en este dashboard</label>
            <input
                id="refine-instruction"
                v-model="form.instruction"
                type="text"
                maxlength="500"
                autocomplete="off"
                :disabled="locked"
                class="h-9 min-w-0 flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-0 disabled:cursor-not-allowed"
                :placeholder="
                    locked
                        ? 'Resuelve la propuesta de abajo para pedir otro ajuste'
                        : 'Ajustar este dashboard… por ejemplo: «cambia las barras por un gráfico circular»'
                "
                :aria-invalid="form.errors.instruction ? 'true' : undefined"
            />
            <button
                type="submit"
                :disabled="!canSubmit"
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground"
                aria-label="Proponer el cambio"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" />
                <ArrowUp v-else class="size-4" aria-hidden="true" />
            </button>
        </form>
        <InputError class="mt-2 px-4" :message="form.errors.instruction" />

        <div v-if="!locked" class="mt-3 flex flex-wrap gap-2 px-1">
            <button
                v-for="suggestion in suggestions"
                :key="suggestion"
                type="button"
                class="rounded-full border bg-card px-3 py-1 text-xs text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @click="form.instruction = suggestion"
            >
                {{ suggestion }}
            </button>
        </div>
    </section>
</template>
