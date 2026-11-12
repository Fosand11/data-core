<script setup lang="ts">
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { LoaderCircle } from 'lucide-vue-next';
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'radix-vue';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel?: string;
        destructive?: boolean;
        loading?: boolean;
    }>(),
    {
        confirmLabel: 'Confirmar',
        destructive: false,
        loading: false,
    },
);

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-50 bg-foreground/25 backdrop-blur-[3px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <AlertDialogContent
                class="fixed left-1/2 top-1/2 z-50 w-[min(92vw,26rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-border/80 bg-popover p-6 shadow-2xl duration-150 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <AlertDialogTitle class="text-lg font-semibold tracking-tight text-foreground">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription class="mt-2 text-sm leading-6 text-muted-foreground">{{ description }}</AlertDialogDescription>

                <div class="mt-6 flex justify-end gap-2">
                    <AlertDialogCancel :class="cn(buttonVariants({ variant: 'outline' }), 'rounded-full px-5')" :disabled="loading"
                        >Cancelar</AlertDialogCancel
                    >
                    <!-- A plain button (not AlertDialogAction) so the dialog stays open while the request runs. -->
                    <button
                        type="button"
                        :disabled="loading"
                        :class="cn(buttonVariants({ variant: destructive ? 'destructive' : 'default' }), 'rounded-full px-5')"
                        @click="emit('confirm')"
                    >
                        <LoaderCircle v-if="loading" class="animate-spin" aria-hidden="true" />
                        {{ confirmLabel }}
                    </button>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
