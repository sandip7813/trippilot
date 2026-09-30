<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from '@lucide/vue';
import AuthStatus from '@/components/auth/AuthStatus.vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot your password?',
        description:
            "No worries. Enter the email you signed up with and we'll send you a link to reset it.",
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div class="flex flex-col gap-8">
        <div>
            <AuthStatus :message="status" />

            <Form
                v-bind="email.form()"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <IconField :icon="Mail">
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            autofocus
                            placeholder="you@example.com"
                            class="h-11 pl-10"
                        />
                    </IconField>
                    <InputError :message="errors.email" />
                </div>

                <Button
                    size="lg"
                    class="h-11 w-full text-base shadow-md shadow-primary/20"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Send reset link
                </Button>
            </Form>
        </div>

        <div class="border-t border-border/70 pt-6 text-center">
            <Link
                :href="login()"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Back to log in
            </Link>
        </div>
    </div>
</template>
