<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, Lock, Mail } from '@lucide/vue';
import AuthStatus from '@/components/auth/AuthStatus.vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Log in to pick up where you left off.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <AuthStatus :message="status" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-8"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <IconField :icon="Mail">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <IconField :icon="Lock">
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="errors.password" />
            </div>

            <Label
                for="remember"
                class="flex w-fit items-center gap-3 font-normal text-muted-foreground"
            >
                <Checkbox id="remember" name="remember" :tabindex="3" />
                Keep me logged in
            </Label>

            <Button
                type="submit"
                size="lg"
                class="mt-2 h-11 w-full text-base shadow-md shadow-primary/20"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
                <ArrowRight v-if="!processing" class="size-4" />
            </Button>
        </div>

        <div
            class="border-t border-border/70 pt-6 text-center text-sm text-muted-foreground"
        >
            New to TripPilot?
            <TextLink :href="register()" :tabindex="6" class="font-medium"
                >Create a free account</TextLink
            >
        </div>
    </Form>
</template>
