<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Mail, MailCheck, Phone } from '@lucide/vue';
import { ref } from 'vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRecaptchaV3 } from '@/composables/useRecaptchaV3';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    recaptcha: {
        enabled: boolean;
        siteKey: string | null;
    };
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Start planning in minutes. No credit card needed.',
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    mobile_number: '',
    email: '',
    'g-recaptcha-response': '',
});

const captchaSubmitting = ref(false);

const { execute: executeRecaptcha } = useRecaptchaV3(() =>
    props.recaptcha.enabled ? props.recaptcha.siteKey : null,
);

/**
 * reCAPTCHA v3 tokens expire after two minutes, so a fresh one is requested
 * right before every submission instead of when the page loads.
 */
async function submit(): Promise<void> {
    form.clearErrors('g-recaptcha-response');

    if (props.recaptcha.enabled && props.recaptcha.siteKey) {
        captchaSubmitting.value = true;

        try {
            form['g-recaptcha-response'] = await executeRecaptcha('register');
        } catch {
            form.setError(
                'g-recaptcha-response',
                'Captcha could not be loaded. Please refresh the page and try again.',
            );

            return;
        } finally {
            captchaSubmitting.value = false;
        }
    }

    form.submit(store());
}
</script>

<template>
    <Head title="Sign up" />

    <form class="flex flex-col gap-8" @submit.prevent="submit">
        <div class="grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2 sm:gap-4">
                <div class="grid gap-2">
                    <Label for="first_name">First name</Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        name="first_name"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="given-name"
                        placeholder="Jane"
                        class="h-11"
                    />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        name="last_name"
                        required
                        :tabindex="2"
                        autocomplete="family-name"
                        placeholder="Doe"
                        class="h-11"
                    />
                    <InputError :message="form.errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <IconField :icon="Mail">
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="mobile_number">Mobile number</Label>
                <IconField :icon="Phone">
                    <Input
                        id="mobile_number"
                        v-model="form.mobile_number"
                        type="tel"
                        name="mobile_number"
                        required
                        :tabindex="4"
                        autocomplete="tel"
                        placeholder="+91 98765 43210"
                        class="h-11 pl-10"
                    />
                </IconField>
                <InputError :message="form.errors.mobile_number" />
            </div>

            <div
                class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-muted-foreground"
            >
                <MailCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                <p>
                    We'll email you a one-time password. You'll choose your own
                    password the first time you log in.
                </p>
            </div>

            <InputError :message="form.errors['g-recaptcha-response']" />

            <Button
                type="submit"
                size="lg"
                class="h-11 w-full text-base shadow-md shadow-primary/20"
                :tabindex="5"
                :disabled="form.processing || captchaSubmitting"
                data-test="register-user-button"
            >
                <Spinner v-if="form.processing || captchaSubmitting" />
                Create account
                <ArrowRight
                    v-if="!form.processing && !captchaSubmitting"
                    class="size-4"
                />
            </Button>
        </div>

        <div
            class="border-t border-border/70 pt-6 text-center text-sm text-muted-foreground"
        >
            Already have an account?
            <TextLink :href="login()" :tabindex="6" class="font-medium"
                >Log in</TextLink
            >
        </div>
    </form>
</template>
