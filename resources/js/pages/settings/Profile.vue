<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, IdCard, MailWarning, Save } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import LocationField from '@/components/LocationField.vue';
import MobileNumberField from '@/components/MobileNumberField.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { CountryDialCodeOption } from '@/types';
import type { TripLocation } from '@/types/trip';

const { mustVerifyEmail, status, homeCity, countryDialCodes } = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    homeCity?: TripLocation | null;
    countryDialCodes: CountryDialCodeOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const homeCityLocation = ref<TripLocation | null>(homeCity ?? null);
const mobileCountryCode = ref(user.value.mobile_country_code ?? '+91');
const mobileNumber = ref(user.value.mobile_number ?? '');
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile settings</h1>

        <SettingsCard
            title="Profile"
            description="Update your name, email, and default home city for new trips"
            :icon="IdCard"
            accent="teal"
        >
            <Form
                v-bind="ProfileController.update.form()"
                class="flex flex-col gap-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            class="mt-1 block w-full"
                            name="first_name"
                            :default-value="user.first_name"
                            required
                            autocomplete="given-name"
                            placeholder="First name"
                        />
                        <InputError class="mt-2" :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            class="mt-1 block w-full"
                            name="last_name"
                            :default-value="user.last_name"
                            required
                            autocomplete="family-name"
                            placeholder="Last name"
                        />
                        <InputError class="mt-2" :message="errors.last_name" />
                    </div>
                </div>

                <MobileNumberField
                    v-model:country-code="mobileCountryCode"
                    v-model:mobile-number="mobileNumber"
                    :country-dial-codes="countryDialCodes"
                    :error="errors.mobile_number ?? errors.mobile_country_code"
                />

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Email address"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <div
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-500/20 dark:bg-amber-500/10"
                >
                    <MailWarning
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                    />
                    <div class="space-y-2">
                        <p class="text-amber-900 dark:text-amber-100">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="font-medium underline decoration-amber-400 underline-offset-4 transition-colors hover:decoration-current"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        <p
                            v-if="status === 'verification-link-sent'"
                            class="font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            A new verification link has been sent to your email
                            address.
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-dashed border-cyan-300/70 bg-linear-to-br from-cyan-50 to-sky-50 p-4 dark:border-cyan-500/20 dark:from-cyan-500/5 dark:to-sky-500/5"
                >
                    <LocationField
                        v-model="homeCityLocation"
                        prefix="home_city"
                        label="Home city"
                        variant="compact"
                        :errors="errors"
                        hint="Used as the default starting point when you create a new trip."
                    />
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="brand-gradient border-0 text-white shadow-md shadow-cyan-500/25 transition hover:opacity-90 hover:shadow-lg"
                        data-test="update-profile-button"
                    >
                        <Save class="size-4" />
                        Save changes
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
                            Saved
                        </p>
                    </Transition>
                </div>
            </Form>
        </SettingsCard>

        <DeleteUser />
    </div>
</template>
