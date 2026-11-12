<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import AppLabel from '@/components/AppLabel.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout
        title="Recupera tu contraseña"
        description="Ingresa tu correo electrónico registrado y te enviaremos instrucciones para restablecerla."
        promo-title="Recupera el acceso en un minuto"
    >
        <Head title="Recuperar contraseña" />

        <div class="mb-7 flex size-16 items-center justify-center rounded-2xl bg-primary-subtle text-primary" aria-hidden="true">
            <Mail class="size-7" />
        </div>

        <div v-if="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-success" role="status">
            {{ status }}
        </div>

        <div class="space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-2">
                    <AppLabel for="email">Correo electrónico</AppLabel>
                    <AppInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        placeholder="tu@empresa.com"
                        :invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />
                    <InputError id="email-error" :message="form.errors.email" />
                </div>

                <div class="mt-7">
                    <AppButton type="submit" class="w-full" :loading="form.processing">Enviar instrucciones</AppButton>
                </div>
            </form>

            <div class="text-center text-sm">
                <TextLink :href="route('login')" class="inline-flex items-center gap-2 font-semibold text-primary">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Volver a iniciar sesión
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
