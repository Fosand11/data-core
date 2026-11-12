<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { watch } from 'vue';

const props = defineProps<{
    dashboardId: string;
    title: string;
    description: string | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({ title: props.title, description: props.description ?? '' });

// Start from the current values every time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) return;

    form.title = props.title;
    form.description = props.description ?? '';
    form.clearErrors();
});

const submit = () => {
    form.patch(route('dashboards.update', { dashboard: props.dashboardId }), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
};
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-foreground/25 backdrop-blur-[3px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 w-[min(92vw,30rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-border/80 bg-popover p-6 shadow-2xl duration-150 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <DialogTitle class="text-lg font-semibold tracking-tight text-foreground">Editar detalles</DialogTitle>
                <DialogDescription class="mt-1 text-sm text-muted-foreground">Cambia el nombre y la descripción de este dashboard.</DialogDescription>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="dashboard-title" class="text-sm font-medium text-foreground">Título</label>
                        <input
                            id="dashboard-title"
                            v-model="form.title"
                            type="text"
                            maxlength="120"
                            required
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-card px-3.5 text-sm text-foreground focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring/30"
                            :aria-invalid="form.errors.title ? 'true' : undefined"
                        />
                        <InputError class="mt-1.5" :message="form.errors.title" />
                    </div>
                    <div>
                        <label for="dashboard-description" class="text-sm font-medium text-foreground"
                            >Descripción <span class="font-normal text-muted-foreground">(opcional)</span></label
                        >
                        <textarea
                            id="dashboard-description"
                            v-model="form.description"
                            rows="3"
                            maxlength="500"
                            class="mt-1.5 w-full resize-none rounded-xl border border-input bg-card px-3.5 py-2.5 text-sm leading-6 text-foreground focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring/30"
                            :aria-invalid="form.errors.description ? 'true' : undefined"
                        />
                        <InputError class="mt-1.5" :message="form.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <DialogClose :class="cn(buttonVariants({ variant: 'outline' }), 'rounded-full px-5')" :disabled="form.processing"
                            >Cancelar</DialogClose
                        >
                        <button
                            type="submit"
                            :disabled="form.processing || form.title.trim() === ''"
                            :class="cn(buttonVariants(), 'rounded-full px-5')"
                        >
                            <LoaderCircle v-if="form.processing" class="animate-spin" aria-hidden="true" />
                            Guardar
                        </button>
                    </div>
                </form>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
