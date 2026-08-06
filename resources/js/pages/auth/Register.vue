<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import AppLabel from '@/components/AppLabel.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        title="Crea tu cuenta"
        description="Empieza a preparar tu espacio de analítica para trabajar con tu equipo."
        promo-title="Analítica clara y unificada para todo tu equipo"
    >
        <Head title="Crear cuenta" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <AppLabel for="name">Nombre completo</AppLabel>
                    <AppInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Tu nombre"
                        :invalid="Boolean(form.errors.name)"
                        :aria-describedby="form.errors.name ? 'name-error' : undefined"
                    />
                    <InputError id="name-error" :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <AppLabel for="email">Correo electrónico</AppLabel>
                    <AppInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="tu@empresa.com"
                        :invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />
                    <InputError id="email-error" :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <AppLabel for="password">Contraseña</AppLabel>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="Crea una contraseña"
                        :invalid="Boolean(form.errors.password)"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                    />
                    <InputError id="password-error" :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <AppLabel for="password_confirmation">Confirma tu contraseña</AppLabel>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <AppButton type="submit" class="mt-1 w-full" :loading="form.processing">
                    Crear cuenta
                    <ArrowRight v-if="!form.processing" aria-hidden="true" />
                </AppButton>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink :href="route('login')" class="font-semibold text-primary">Inicia sesión</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
