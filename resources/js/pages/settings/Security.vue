<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    KeyRound,
    LockKeyhole,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});

const passwordTips = [
    'Use at least 12 characters',
    'Mix letters, numbers & symbols',
    "Don't reuse passwords from other sites",
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Security settings" />

        <h1 class="sr-only">Security settings</h1>

        <SettingsCard
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
            :icon="LockKeyhole"
            accent="violet"
        >
            <div
                class="flex flex-col gap-3 rounded-xl bg-linear-to-br from-violet-50 via-fuchsia-50 to-sky-50 p-4 dark:from-violet-500/10 dark:via-fuchsia-500/5 dark:to-sky-500/5"
            >
                <p
                    class="flex items-center gap-2 text-sm font-semibold text-violet-700 dark:text-violet-300"
                >
                    <Sparkles class="size-4" />
                    Tips for a strong password
                </p>
                <ul class="grid gap-2 sm:grid-cols-3">
                    <li
                        v-for="tip in passwordTips"
                        :key="tip"
                        class="flex items-start gap-2 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="mt-px size-3.5 shrink-0 text-fuchsia-500"
                        />
                        {{ tip }}
                    </li>
                </ul>
            </div>

            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="flex flex-col gap-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="current_password">Current password</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        placeholder="Current password"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="New password"
                            :passwordrules="props.passwordRules"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirm password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                            :passwordrules="props.passwordRules"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="border-0 bg-linear-to-r from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/25 transition hover:opacity-90 hover:shadow-lg"
                        data-test="update-password-button"
                    >
                        <KeyRound class="size-4" />
                        Update password
                    </Button>

                    <Transition
                        enter-active-class="transition ease-out duration-300"
                        enter-from-class="opacity-0 translate-x-2"
                        leave-active-class="transition ease-in duration-200"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-4" />
                            Password updated
                        </p>
                    </Transition>
                </div>
            </Form>
        </SettingsCard>
    </div>
</template>
