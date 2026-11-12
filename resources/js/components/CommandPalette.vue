<script setup lang="ts">
import DashboardStatusBadge from '@/components/DashboardStatusBadge.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useCommandPalette } from '@/composables/useCommandPalette';
import type { DashboardSummary, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, LoaderCircle, Monitor, Moon, Plus, Search, Settings, Sparkles, Sun, WandSparkles, type LucideIcon } from 'lucide-vue-next';
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Entry {
    id: string;
    group: 'Dashboards' | 'Recientes' | 'Acciones' | 'Preguntar';
    label: string;
    icon: LucideIcon;
    status?: DashboardSummary['status'];
    run: () => void;
}

const { open, toggle, hide } = useCommandPalette();
const { updateAppearance } = useAppearance();
const page = usePage<SharedData>();

const query = ref('');
const results = ref<DashboardSummary[]>([]);
const loading = ref(false);
const failed = ref(false);
const activeIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLElement | null>(null);

const term = computed(() => query.value.trim());

const fold = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const go = (url: string) => () => {
    hide();
    router.visit(url);
};

const actions = computed<Entry[]>(() => {
    const all: Entry[] = [
        { id: 'a-new', group: 'Acciones', label: 'Nueva pregunta', icon: Plus, run: go(route('dashboards.create')) },
        { id: 'a-home', group: 'Acciones', label: 'Ir a Inicio', icon: Sparkles, run: go(route('dashboard')) },
        { id: 'a-dashboards', group: 'Acciones', label: 'Ir a Dashboards', icon: LayoutGrid, run: go(route('dashboards.index')) },
        { id: 'a-settings', group: 'Acciones', label: 'Configuración', icon: Settings, run: go(route('profile.edit')) },
        { id: 'a-light', group: 'Acciones', label: 'Tema claro', icon: Sun, run: () => setTheme('light') },
        { id: 'a-dark', group: 'Acciones', label: 'Tema oscuro', icon: Moon, run: () => setTheme('dark') },
        { id: 'a-system', group: 'Acciones', label: 'Tema automático', icon: Monitor, run: () => setTheme('system') },
    ];

    if (term.value === '') return all;

    const needle = fold(term.value);

    return all.filter((action) => fold(action.label).includes(needle));
});

function setTheme(value: 'light' | 'dark' | 'system') {
    updateAppearance(value);
    hide();
}

const dashboardEntries = computed<Entry[]>(() => {
    const source: Array<Pick<DashboardSummary, 'id' | 'title' | 'status'>> = term.value === '' ? (page.props.recentDashboards ?? []) : results.value;
    const group = term.value === '' ? 'Recientes' : 'Dashboards';

    return source.map((dashboard) => ({
        id: `d-${dashboard.id}`,
        group,
        label: dashboard.title,
        icon: LayoutGrid,
        status: dashboard.status,
        run: go(route('dashboards.show', { dashboard: dashboard.id })),
    }));
});

// Turn a typed sentence into a new dashboard: search and generation in one box.
const askEntry = computed<Entry[]>(() =>
    term.value.length >= 10
        ? [
              {
                  id: 'ask',
                  group: 'Preguntar',
                  label: `Crear con IA: “${term.value}”`,
                  icon: WandSparkles,
                  run: () => {
                      hide();
                      router.get(route('dashboards.create'), { prompt: term.value });
                  },
              },
          ]
        : [],
);

const entries = computed<Entry[]>(() => [...dashboardEntries.value, ...actions.value, ...askEntry.value]);

const sections = computed(() => {
    const grouped: Array<{ title: string; items: Array<{ entry: Entry; index: number }> }> = [];

    entries.value.forEach((entry, index) => {
        let section = grouped.find((candidate) => candidate.title === entry.group);

        if (!section) {
            section = { title: entry.group, items: [] };
            grouped.push(section);
        }

        section.items.push({ entry, index });
    });

    return grouped;
});

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
let sequence = 0;

async function search(value: string) {
    controller?.abort();
    controller = new AbortController();
    const current = ++sequence;
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(route('dashboards.search', { q: value }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const body = (await response.json()) as { data: DashboardSummary[] };

        if (current === sequence) results.value = body.data;
    } catch (error) {
        if ((error as Error).name === 'AbortError') return;

        if (current === sequence) {
            results.value = [];
            failed.value = true;
        }
    } finally {
        if (current === sequence) loading.value = false;
    }
}

watch(query, () => {
    activeIndex.value = 0;
    clearTimeout(timer);

    if (term.value === '') {
        controller?.abort();
        sequence++;
        loading.value = false;
        failed.value = false;
        results.value = [];

        return;
    }

    loading.value = true;
    timer = setTimeout(() => search(term.value), 180);
});

watch(entries, (list) => {
    if (activeIndex.value >= list.length) activeIndex.value = Math.max(0, list.length - 1);
});

watch(open, async (isOpen) => {
    if (!isOpen) {
        clearTimeout(timer);
        controller?.abort();

        return;
    }

    query.value = '';
    results.value = [];
    activeIndex.value = 0;
    await nextTick();
    inputRef.value?.focus();
});

watch(activeIndex, async (index) => {
    await nextTick();
    listRef.value?.querySelector(`#cp-option-${index}`)?.scrollIntoView({ block: 'nearest' });
});

function onKeydown(event: KeyboardEvent) {
    const count = entries.value.length;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (count) activeIndex.value = (activeIndex.value + 1) % count;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (count) activeIndex.value = (activeIndex.value - 1 + count) % count;
    } else if (event.key === 'Enter' && !event.isComposing) {
        event.preventDefault();
        entries.value[activeIndex.value]?.run();
    }
}

function onGlobalKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        toggle();
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    clearTimeout(timer);
    controller?.abort();
});

const showEmpty = computed(() => term.value !== '' && !loading.value && !failed.value && dashboardEntries.value.length === 0);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-foreground/20 backdrop-blur-[3px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-[12vh] z-50 flex max-h-[min(34rem,76vh)] w-[min(92vw,38rem)] -translate-x-1/2 flex-col overflow-hidden rounded-2xl border border-border/80 bg-popover/95 shadow-2xl backdrop-blur-xl duration-150 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
                @open-auto-focus.prevent="inputRef?.focus()"
            >
                <DialogTitle class="sr-only">Buscar y ejecutar acciones</DialogTitle>
                <DialogDescription class="sr-only">Busca dashboards de tu organización o ejecuta una acción con el teclado.</DialogDescription>

                <div class="flex items-center gap-3 border-b px-4">
                    <LoaderCircle v-if="loading" class="size-[1.1rem] shrink-0 animate-spin text-muted-foreground" aria-hidden="true" />
                    <Search v-else class="size-[1.1rem] shrink-0 text-muted-foreground" aria-hidden="true" />
                    <input
                        ref="inputRef"
                        v-model="query"
                        type="text"
                        role="combobox"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        maxlength="100"
                        class="h-14 min-w-0 flex-1 bg-transparent text-base text-foreground outline-none placeholder:text-muted-foreground"
                        placeholder="Buscar dashboards o escribir una pregunta…"
                        aria-label="Buscar"
                        aria-expanded="true"
                        aria-controls="command-palette-list"
                        :aria-activedescendant="entries.length ? `cp-option-${activeIndex}` : undefined"
                        @keydown="onKeydown"
                    />
                    <kbd class="hidden rounded-md border bg-muted px-1.5 py-0.5 text-[0.65rem] font-medium text-muted-foreground sm:block">esc</kbd>
                </div>

                <div id="command-palette-list" ref="listRef" role="listbox" aria-label="Resultados" class="min-h-0 flex-1 overflow-y-auto p-2">
                    <p v-if="failed" class="px-3 py-6 text-center text-sm text-muted-foreground" role="alert">
                        No se pudo buscar ahora. Inténtalo de nuevo.
                    </p>
                    <p v-else-if="showEmpty && entries.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                        Sin resultados para “{{ term }}”.
                    </p>

                    <div v-for="section in sections" :key="section.title" class="mb-1 last:mb-0" role="group" :aria-label="section.title">
                        <p class="px-3 pb-1 pt-2 text-xs font-medium text-muted-foreground">{{ section.title }}</p>
                        <div
                            v-for="{ entry, index } in section.items"
                            :id="`cp-option-${index}`"
                            :key="entry.id"
                            role="option"
                            :aria-selected="index === activeIndex"
                            :class="[
                                'flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors',
                                index === activeIndex ? 'bg-accent text-foreground' : 'text-foreground/90',
                            ]"
                            @mousemove="activeIndex = index"
                            @click="entry.run()"
                        >
                            <span
                                :class="[
                                    'flex size-7 shrink-0 items-center justify-center rounded-lg',
                                    index === activeIndex ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <component :is="entry.icon" class="size-4" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ entry.label }}</span>
                            <DashboardStatusBadge v-if="entry.status && entry.status !== 'ready'" :status="entry.status" />
                            <kbd
                                v-if="index === activeIndex"
                                class="hidden rounded-md border bg-background px-1.5 py-0.5 text-[0.65rem] font-medium text-muted-foreground sm:block"
                                >↵</kbd
                            >
                        </div>
                    </div>

                    <p v-if="showEmpty && entries.length > 0" class="px-3 pb-2 pt-3 text-xs text-muted-foreground">
                        Ningún dashboard coincide con “{{ term }}”.
                    </p>
                </div>

                <div class="hidden items-center gap-4 border-t bg-muted/40 px-4 py-2 text-xs text-muted-foreground sm:flex">
                    <span><kbd class="font-sans">↑↓</kbd> navegar</span>
                    <span><kbd class="font-sans">↵</kbd> abrir</span>
                    <span><kbd class="font-sans">esc</kbd> cerrar</span>
                    <span class="ml-auto"><kbd class="font-sans">⌘K</kbd> abrir en cualquier momento</span>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
