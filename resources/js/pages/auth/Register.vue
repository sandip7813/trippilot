<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRecaptchaV3 } from '@/composables/useRecaptchaV3';
import { login } from '@/routes';
import { otp as sendRegistrationOtp, store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    recaptcha: {
        enabled: boolean;
        siteKey: string | null;
    };
    otpStatus?: {
        sent: boolean;
        email: string | null;
    };
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

const email = ref(props.otpStatus?.email ?? '');
const recaptchaToken = ref('');
const skipRecaptchaOnce = ref(false);
const captchaSubmitting = ref(false);
const otpSendForm = useForm({ email: '' });

const { execute: executeRecaptcha } = useRecaptchaV3(
    () => props.recaptcha.siteKey,
);

function requestOtp(): void {
    otpSendForm.email = email.value;
    otpSendForm.post(sendRegistrationOtp(), {
        preserveScroll: true,
    });
}

async function handleSubmit(event: Event): Promise<void> {
    if (
        ! props.recaptcha.enabled
        || ! props.recaptcha.siteKey
        || skipRecaptchaOnce.value
    ) {
        skipRecaptchaOnce.value = false;

        return;
    }

    event.preventDefault();
    captchaSubmitting.value = true;

    try {
        recaptchaToken.value = await executeRecaptcha('register');
        skipRecaptchaOnce.value = true;
        await nextTick();
        (event.currentTarget as HTMLFormElement).requestSubmit();
    } finally {
        captchaSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation', 'otp']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @submit="handleSubmit"
    >
        <input
            type="hidden"
            name="g-recaptcha-response"
            :value="recaptchaToken"
        />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                        class="flex-1"
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        class="shrink-0"
                        :disabled="otpSendForm.processing || !email"
                        @click="requestOtp"
                    >
                        <Spinner v-if="otpSendForm.processing" />
                        Send code
                    </Button>
                </div>
                <InputError :message="errors.email" />
                <InputError :message="otpSendForm.errors.email" />
                <p
                    v-if="otpStatus?.sent"
                    class="text-sm font-medium text-green-600"
                >
                    Verification code sent to {{ otpStatus.email }}.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="otp">Email verification code</Label>
                <Input
                    id="otp"
                    type="text"
                    required
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    :tabindex="3"
                    autocomplete="one-time-code"
                    name="otp"
                    placeholder="6-digit code"
                />
                <InputError :message="errors.otp" />
                <p class="text-xs text-muted-foreground">
                    Enter the code we emailed you. Codes expire after 10
                    minutes.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <InputError :message="errors['g-recaptcha-response']" />

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing || captchaSubmitting"
                data-test="register-user-button"
            >
                <Spinner v-if="processing || captchaSubmitting" />
                Create account
            </Button>

            <p
                v-if="recaptcha.enabled && recaptcha.siteKey"
                class="text-center text-xs text-muted-foreground"
            >
                This site is protected by reCAPTCHA and the Google
                <a
                    href="https://policies.google.com/privacy"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-4"
                    >Privacy Policy</a
                >
                and
                <a
                    href="https://policies.google.com/terms"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-4"
                    >Terms of Service</a
                >
                apply.
            </p>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
