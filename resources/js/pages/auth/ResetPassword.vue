<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Lock, Mail } from '@lucide/vue';
import { ref } from 'vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Set a new password',
        description: 'Choose a strong password you have not used before.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <IconField :icon="Mail">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="h-11 bg-muted/50 pl-10 text-muted-foreground"
                        readonly
                    />
                </IconField>
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <IconField :icon="Lock">
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        class="h-11 pl-10"
                        autofocus
                        placeholder="Password"
                        :passwordrules="passwordRules"
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
                        autocomplete="new-password"
                        class="h-11 pl-10"
                        placeholder="Confirm password"
                        :passwordrules="passwordRules"
                    />
                </IconField>
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="mt-2 h-11 w-full text-base shadow-md shadow-primary/20"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Reset password
            </Button>
        </div>
    </Form>
</template>
