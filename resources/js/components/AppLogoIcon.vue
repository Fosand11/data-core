<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- An open "A" with a data point inside it: the small thing a glimpse (atisbo) picks out. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs" fill="none">
        <path d="M6.15 17.55L12 6.45L17.85 17.55" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="12" cy="14.55" r="1.75" fill="#F2B53A" />
    </svg>
</template>
