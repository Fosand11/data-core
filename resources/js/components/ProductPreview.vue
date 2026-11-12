<script setup lang="ts">
// Decorative, static mock of a dashboard drawn with the design tokens. It never shows real data.
const bars = [38, 62, 46, 78, 55, 90];
</script>

<template>
    <div class="overflow-hidden rounded-2xl border bg-card shadow-soft" aria-hidden="true">
        <div class="flex items-center gap-1.5 border-b px-4 py-3">
            <span class="size-2.5 rounded-full bg-muted-foreground/25" />
            <span class="size-2.5 rounded-full bg-muted-foreground/25" />
            <span class="size-2.5 rounded-full bg-muted-foreground/25" />
            <span class="mx-auto text-xs font-medium text-muted-foreground">Ventas por región</span>
        </div>

        <div class="grid gap-3 p-4 sm:grid-cols-3">
            <div
                v-for="tile in [
                    ['Ventas totales', '$444,120'],
                    ['Pedidos', '600'],
                    ['Ticket promedio', '$740.20'],
                ]"
                :key="tile[0]"
                class="rounded-xl border p-4"
            >
                <p class="text-xs text-muted-foreground">{{ tile[0] }}</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight text-foreground">{{ tile[1] }}</p>
            </div>
        </div>

        <div class="grid gap-3 px-4 pb-4 sm:grid-cols-5">
            <div class="rounded-xl border p-4 sm:col-span-3">
                <p class="text-xs font-medium text-foreground">Ventas por región</p>
                <div class="mt-4 flex h-32 items-end gap-3">
                    <span
                        v-for="(height, index) in bars"
                        :key="index"
                        class="flex-1 rounded-t-md"
                        :style="{ height: `${height}%`, backgroundColor: 'var(--viz-1)' }"
                    />
                </div>
            </div>
            <div class="rounded-xl border p-4 sm:col-span-2">
                <p class="text-xs font-medium text-foreground">Evolución mensual</p>
                <svg viewBox="0 0 200 110" class="mt-4 h-32 w-full" fill="none" preserveAspectRatio="none">
                    <path d="M0 90 L33 80 L66 84 L100 55 L133 62 L166 28 L200 38 V110 H0 Z" fill="var(--viz-1)" opacity="0.12" />
                    <path
                        d="M0 90 L33 80 L66 84 L100 55 L133 62 L166 28 L200 38"
                        stroke="var(--viz-1)"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>
            </div>
        </div>
    </div>
</template>
