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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs" fill="none">
        <path d="M12 3V6M12 18V21M3 12H6M18 12H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        <path
            d="M8.2 8.1C9.25 7.05 10.72 6.5 12.2 6.62C13.69 6.74 15.05 7.52 15.92 8.73C16.79 9.94 17.1 11.47 16.76 12.92C16.42 14.37 15.47 15.6 14.15 16.29C12.83 16.98 11.27 17.06 9.89 16.5C8.51 15.94 7.43 14.8 6.95 13.39"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
        />
        <path d="M4.75 5.25L6.15 6.65M17.85 17.35L19.25 18.75M18.75 5.25L17.35 6.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>
</template>
