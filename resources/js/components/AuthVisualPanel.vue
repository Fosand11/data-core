<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
    }>(),
    {
        title: 'De preguntas simples a decisiones más claras',
    },
);
</script>

<template>
    <aside class="relative hidden min-h-svh overflow-hidden bg-[#0f172a] px-10 py-12 text-white lg:flex lg:flex-col" aria-hidden="true">
        <div class="absolute inset-x-0 top-1/4 h-96 bg-[radial-gradient(circle_at_center,rgba(79,70,229,0.28),transparent_68%)]" />
        <div class="relative mx-auto mt-4 w-full max-w-3xl rounded-2xl border border-white/10 bg-[#111827] p-5 shadow-2xl">
            <div class="flex items-center gap-2 border-b border-white/10 pb-4">
                <span class="size-2.5 rounded-full bg-red-400" />
                <span class="size-2.5 rounded-full bg-amber-400" />
                <span class="size-2.5 rounded-full bg-emerald-400" />
                <div class="ml-4 h-7 flex-1 rounded-md bg-white/5" />
                <span class="rounded bg-emerald-500/10 px-2 py-1 text-[10px] font-medium text-emerald-300">Vista previa</span>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="h-2 w-24 rounded bg-white/20" />
                    <div class="mt-4 h-6 w-16 rounded bg-white/80" />
                    <div class="mt-3 h-2 w-20 rounded bg-emerald-400/60" />
                </div>
                <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="h-2 w-20 rounded bg-white/20" />
                    <div class="mt-4 h-6 w-24 rounded bg-white/80" />
                    <div class="mt-3 h-2 w-16 rounded bg-primary/80" />
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-white/10 bg-black/10 p-5">
                <div class="h-3 w-40 rounded bg-white/70" />
                <svg viewBox="0 0 640 150" class="mt-5 h-36 w-full" fill="none">
                    <path d="M10 125H630" stroke="rgba(255,255,255,.08)" />
                    <path d="M10 80H630" stroke="rgba(255,255,255,.08)" />
                    <path d="M10 35H630" stroke="rgba(255,255,255,.08)" />
                    <path
                        d="M20 118L120 92L220 106L320 58L420 76L520 31L620 48"
                        stroke="#8B5CF6"
                        stroke-width="4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <g fill="#4F46E5">
                        <rect x="45" y="78" width="24" height="50" rx="4" />
                        <rect x="150" y="58" width="24" height="70" rx="4" />
                        <rect x="255" y="88" width="24" height="40" rx="4" />
                        <rect x="360" y="40" width="24" height="88" rx="4" />
                        <rect x="465" y="68" width="24" height="60" rx="4" />
                        <rect x="570" y="48" width="24" height="80" rx="4" />
                    </g>
                </svg>
            </div>
        </div>

        <div class="relative mx-auto mt-auto max-w-2xl pb-6 pt-12 text-center">
            <h2 class="text-2xl font-bold leading-tight">{{ title }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-slate-400">
                InsightFlow prepara una experiencia clara para explorar información y convertir preguntas en dashboards cuando la generación esté
                disponible.
            </p>
        </div>
    </aside>
</template>
