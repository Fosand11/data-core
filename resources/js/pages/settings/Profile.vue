<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Identidad',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Identidad" />

        <SettingsLayout>
            <section class="insight-panel p-6 sm:p-8" aria-labelledby="profile-title">
                <div class="border-b border-border/70 pb-5">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-primary">Identidad visible</p>
                    <h2 id="profile-title" class="mt-2 text-2xl font-semibold tracking-tight text-foreground">Cómo te reconoce el espacio</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">Estos datos aparecen en tu cuenta y en los recursos que creas.</p>
                </div>

                <form class="mt-6 space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            class="mt-1 block h-12 w-full rounded-xl bg-background"
                            required
                            autocomplete="name"
                            placeholder="Tu nombre completo"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block h-12 w-full rounded-xl bg-background"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="tu@correo.com"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-foreground">
                            Tu correo todavía no ha sido verificado.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="rounded-md text-sm font-medium text-primary underline underline-offset-4 hover:text-primary-hover focus:ring-2 focus:ring-offset-2"
                            >
                                Reenviar correo de verificación.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-success">
                            Enviamos un nuevo enlace de verificación a tu correo.
                        </div>
                    </div>

                    <div class="flex items-center gap-4 border-t border-border/70 pt-5">
                        <Button class="rounded-full px-5" :disabled="form.processing">Guardar cambios</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-success">Cambios guardados.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </section>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
