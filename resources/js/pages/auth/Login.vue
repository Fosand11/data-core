<script setup lang="ts">
import AppButton from '@/components/AppButton.vue';
import AppInput from '@/components/AppInput.vue';
import AppLabel from '@/components/AppLabel.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase
        title="Bienvenido de nuevo"
        description="Ingresa tus credenciales para acceder a tus dashboards."
        promo-title="Tus preguntas, convertidas en dashboards"
    >
        <Head title="Iniciar sesión" />

        <div v-if="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-success" role="status">
            {{ status }}
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <AppLabel for="email">Correo electrónico</AppLabel>
                    <AppInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="tu@empresa.com"
                        :invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />
                    <InputError id="email-error" :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <AppLabel for="password">Contraseña</AppLabel>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-primary">
                            ¿Olvidaste tu contraseña?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="Ingresa tu contraseña"
                        :invalid="Boolean(form.errors.password)"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                    />
                    <InputError id="password-error" :message="form.errors.password" />
                </div>

                <div class="flex items-center">
                    <AppLabel for="remember" class="flex cursor-pointer items-center gap-3 font-normal text-muted-foreground">
                        <Checkbox id="remember" v-model:checked="form.remember" />
                        <span>Recordarme en este equipo</span>
                    </AppLabel>
                </div>

                <AppButton type="submit" class="mt-2 w-full" :loading="form.processing">
                    Iniciar sesión
                    <ArrowRight v-if="!form.processing" aria-hidden="true" />
                </AppButton>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿No tienes cuenta?
                <TextLink :href="route('register')" class="font-semibold text-primary">Regístrate gratis</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
