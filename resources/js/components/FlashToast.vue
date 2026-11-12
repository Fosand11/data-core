<script setup lang="ts">
import { showToast, useToast } from '@/composables/useToast';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { watch } from 'vue';

const page = usePage<SharedData>();
const { message } = useToast();

watch(
    () => page.props.flash?.status,
    (status) => {
        if (status) showToast(status);
    },
    { immediate: true },
);
</script>

<template>
    <!-- The live region is always in the DOM so assistive technology announces the text when it appears. -->
    <div class="pointer-events-none fixed inset-x-0 bottom-6 z-[60] flex justify-center px-4 print:hidden" role="status" aria-live="polite">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="message"
                class="pointer-events-auto flex max-w-md items-center gap-2.5 rounded-full border border-border/80 bg-popover/95 py-2.5 pl-3.5 pr-4 text-sm text-foreground shadow-lg backdrop-blur-xl"
            >
                <CircleCheck class="size-4 shrink-0 text-primary" aria-hidden="true" />
                <span>{{ message }}</span>
            </div>
        </Transition>
    </div>
</template>
