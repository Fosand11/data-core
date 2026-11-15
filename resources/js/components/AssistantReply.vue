<script setup lang="ts">
import { visualizationIcons } from '@/lib/visualizationIcons';
import type { AssistantReply } from '@/types';
import { Info, Lightbulb, Sparkles, X } from 'lucide-vue-next';

defineProps<{ reply: AssistantReply }>();

const emit = defineEmits<{
    /** A suggestion or an example was chosen: the caller puts it in its text box. */
    use: [text: string];
    close: [];
}>();
</script>

<template>
    <section
        class="mt-5 rounded-2xl border border-primary/20 bg-card p-5 shadow-card"
        :aria-label="reply.title"
        aria-live="polite"
        data-testid="assistant-reply"
    >
        <header class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-primary">
                    <Sparkles class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold tracking-tight text-foreground">{{ reply.title }}</h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ reply.message }}</p>
                </div>
            </div>
            <button
                type="button"
                class="-mr-1 -mt-1 flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="Cerrar la respuesta del asistente"
                @click="emit('close')"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </header>

        <ul v-if="reply.visuals" class="mt-4 grid gap-2 sm:grid-cols-2" aria-label="Tipos de visual">
            <li
                v-for="visual in reply.visuals"
                :key="visual.type"
                class="flex gap-3 rounded-xl border bg-background/60 p-3"
                :class="visual.available ? '' : 'opacity-60'"
            >
                <component :is="visualizationIcons[visual.type]" class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-foreground">{{ visual.name }}</p>
                    <p class="mt-0.5 text-xs leading-5 text-muted-foreground">{{ visual.description }}</p>
                    <p v-if="!visual.available" class="mt-1 text-xs text-warning">{{ visual.note }}</p>
                    <button
                        v-else
                        type="button"
                        class="mt-1.5 max-w-full rounded-full border bg-card px-2.5 py-1 text-left text-xs text-foreground transition-colors hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @click="emit('use', visual.example)"
                    >
                        «{{ visual.example }}»
                    </button>
                </div>
            </li>
        </ul>

        <div v-if="reply.suggestions.length" class="mt-4">
            <p v-if="reply.visuals" class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Lightbulb class="size-3.5" aria-hidden="true" /> Para empezar
            </p>
            <div class="flex flex-wrap gap-2" role="list" aria-label="Preguntas sugeridas">
                <button
                    v-for="suggestion in reply.suggestions"
                    :key="suggestion"
                    type="button"
                    role="listitem"
                    class="rounded-full border bg-card px-3.5 py-1.5 text-left text-sm text-foreground transition-colors hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @click="emit('use', suggestion)"
                >
                    {{ suggestion }}
                </button>
            </div>
        </div>

        <ul v-if="reply.tips" class="mt-4 space-y-1.5 text-sm text-muted-foreground">
            <li v-for="tip in reply.tips" :key="tip" class="flex gap-2">
                <Info class="mt-1 size-3.5 shrink-0 text-primary" aria-hidden="true" />
                <span>{{ tip }}</span>
            </li>
        </ul>

        <ul v-if="reply.limits" class="mt-4 space-y-2 text-sm text-muted-foreground">
            <li v-for="limit in reply.limits" :key="limit" class="flex gap-2">
                <span class="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground/50" aria-hidden="true" />
                <span>{{ limit }}</span>
            </li>
        </ul>
    </section>
</template>
