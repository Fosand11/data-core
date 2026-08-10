<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const deleteUser = (e: Event) => {
    e.preventDefault();

    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="overflow-hidden rounded-[1.25rem] border border-error/15 bg-card" aria-labelledby="delete-account-title">
        <div class="grid gap-5 p-6 sm:grid-cols-[1fr_auto] sm:items-center sm:p-8">
            <div>
                <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-error">Zona irreversible</p>
                <h2 id="delete-account-title" class="mt-2 text-xl font-semibold tracking-tight text-foreground">Eliminar esta cuenta</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                    Esta acción elimina permanentemente tu cuenta y no se puede deshacer.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button class="rounded-full" variant="destructive">Eliminar cuenta</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>¿Quieres eliminar tu cuenta?</DialogTitle>
                            <DialogDescription>
                                La cuenta y sus datos se eliminarán permanentemente. Escribe tu contraseña para confirmar esta decisión.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">Contraseña</Label>
                            <Input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                name="password"
                                placeholder="Contraseña"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="secondary" @click="closeModal">Cancelar</Button>
                            </DialogClose>

                            <Button type="submit" variant="destructive" :disabled="form.processing">Eliminar definitivamente</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </section>
</template>
