<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Lock, ShieldCheck } from '@lucide/vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { update } from '@/routes/password/change';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Choose a new password',
        description:
            'You logged in with a one-time password. Please set a new password to continue.',
    },
});
</script>

<template>
    <Head title="Change password" />

    <Form
        v-bind="update.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div
            class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-muted-foreground"
        >
            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-primary" />
            <p>
                For your security, replace the one-time password we emailed you
                with one only you know.
            </p>
        </div>

        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <IconField :icon="Lock">
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="New password"
                        :passwordrules="passwordRules"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <IconField :icon="Lock">
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        :passwordrules="passwordRules"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="mt-2 h-11 w-full text-base shadow-md shadow-primary/20"
                :disabled="processing"
                data-test="change-password-button"
            >
                <Spinner v-if="processing" />
                Save password and continue
            </Button>
        </div>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
