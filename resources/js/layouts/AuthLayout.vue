<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

defineProps<{
    title?: string;
    description?: string;
    promoTitle?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :promo-title="promoTitle">
        <slot />
    </AuthLayout>
</template>
