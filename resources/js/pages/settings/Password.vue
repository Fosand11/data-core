<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface Props {
    className?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Acceso',
        href: '/settings/password',
    },
];

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Seguridad de acceso" />

        <SettingsLayout>
            <section class="insight-panel p-6 sm:p-8" aria-labelledby="password-title">
                <div class="border-b border-border/70 pb-5">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-primary">Llave de acceso</p>
                    <h2 id="password-title" class="mt-2 text-2xl font-semibold tracking-tight text-foreground">Renueva tu contraseña</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">Utiliza una contraseña larga, única y difícil de adivinar.</p>
                </div>

                <form class="mt-6 space-y-6" @submit.prevent="updatePassword">
                    <div class="grid gap-2">
                        <Label for="current_password">Contraseña actual</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="mt-1 block h-12 w-full rounded-xl bg-background"
                            autocomplete="current-password"
                            placeholder="Tu contraseña actual"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Nueva contraseña</Label>
                        <Input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block h-12 w-full rounded-xl bg-background"
                            autocomplete="new-password"
                            placeholder="Crea una contraseña segura"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirmar contraseña</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block h-12 w-full rounded-xl bg-background"
                            autocomplete="new-password"
                            placeholder="Repite la nueva contraseña"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4 border-t border-border/70 pt-5">
                        <Button class="rounded-full px-5" :disabled="form.processing">Actualizar contraseña</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-success">Contraseña actualizada.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
