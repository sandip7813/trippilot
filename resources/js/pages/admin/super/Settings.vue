<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Settings, Shield, ShieldCheck } from '@lucide/vue';
import SettingsController from '@/actions/App/Http/Controllers/Admin/Super/SettingsController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import LogoOptionsShowcase from '@/components/LogoOptionsShowcase.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { settings as superSettings } from '@/routes/admin/super';

defineProps<{
    integrations: {
        maps_driver: string;
        weather_driver: string;
        ai_driver: string;
        trains_driver: string;
        trip_covers_driver: string;
        trip_covers_enabled: boolean;
        trip_covers_use_gemini_prompt: boolean;
        trip_covers_pollinations_fallback: boolean;
        gemini_image_enabled: boolean;
        geoapify_api_key_configured: boolean;
        openweathermap_api_key_configured: boolean;
        gemini_api_key_configured: boolean;
        railradar_api_key_configured: boolean;
        unsplash_access_key_configured: boolean;
        recaptcha_enabled: boolean;
        recaptcha_configured: boolean;
        logo: 'plane' | 'compass' | 'pin' | 'globe' | 'monogram';
    };
    integration_statuses: Record<
        string,
        {
            label: string;
            description: string;
            configured: boolean;
            requires_key: boolean;
        }
    >;
    driver_options: Record<string, Array<{ value: string; label: string }>>;
}>();

const selectClass =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

const checkboxClass =
    'size-4 rounded border border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Super Admin', href: superSettings() },
            { title: 'Settings', href: superSettings() },
        ],
    },
});
</script>

<template>
    <Head title="Super Admin Settings" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Super Admin Settings"
            description="System configuration, integrations, and platform controls."
            :icon="ShieldCheck"
        />

        <Form
            v-bind="SettingsController.updateIntegrations.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Saving settings..."
            />

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Settings class="size-4" />
                        Integration status
                    </CardTitle>
                    <CardDescription>
                        Live status based on the active driver and stored API
                        keys. Values saved here override
                        <code class="rounded bg-muted px-1 font-mono text-[11px]"
                            >.env</code
                        >
                        for this app instance.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y divide-border/60">
                        <li
                            v-for="(item, key) in integration_statuses"
                            :key="key"
                            class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ item.label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.description }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    item.configured ? 'default' : 'outline'
                                "
                            >
                                {{
                                    item.configured
                                        ? 'Configured'
                                        : 'Needs setup'
                                }}
                            </Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Shield class="size-4" />
                        Security
                    </CardTitle>
                    <CardDescription>
                        Authentication and abuse-prevention controls for the
                        platform.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-start gap-3">
                        <input type="hidden" name="recaptcha_enabled" value="0" />
                        <input
                            id="recaptcha_enabled"
                            type="checkbox"
                            name="recaptcha_enabled"
                            value="1"
                            :class="checkboxClass"
                            :checked="integrations.recaptcha_enabled"
                        />
                        <div class="grid gap-1">
                            <Label for="recaptcha_enabled"
                                >Enable Google reCAPTCHA v3 on signup</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                When enabled, new registrations require a valid
                                reCAPTCHA v3 token. Site and secret keys are
                                still configured in
                                <code
                                    class="rounded bg-muted px-1 font-mono text-[11px]"
                                    >.env</code
                                >.
                            </p>
                            <p
                                v-if="
                                    integrations.recaptcha_enabled
                                        && !integrations.recaptcha_configured
                                "
                                class="text-xs font-medium text-amber-600 dark:text-amber-400"
                            >
                                reCAPTCHA is enabled but
                                RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY are
                                missing, so signup will skip captcha until keys
                                are added.
                            </p>
                        </div>
                    </div>
                    <InputError :message="errors.recaptcha_enabled" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Drivers & toggles</CardTitle>
                    <CardDescription>
                        Choose active providers and feature switches for the
                        platform.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="maps_driver">Maps driver</Label>
                        <select
                            id="maps_driver"
                            name="maps_driver"
                            :class="selectClass"
                            :default-value="integrations.maps_driver"
                        >
                            <option
                                v-for="option in driver_options.maps"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.maps_driver" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="weather_driver">Weather driver</Label>
                        <select
                            id="weather_driver"
                            name="weather_driver"
                            :class="selectClass"
                            :default-value="integrations.weather_driver"
                        >
                            <option
                                v-for="option in driver_options.weather"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.weather_driver" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ai_driver">AI driver</Label>
                        <select
                            id="ai_driver"
                            name="ai_driver"
                            :class="selectClass"
                            :default-value="integrations.ai_driver"
                        >
                            <option
                                v-for="option in driver_options.ai"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.ai_driver" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="trains_driver">Train timings driver</Label>
                        <select
                            id="trains_driver"
                            name="trains_driver"
                            :class="selectClass"
                            :default-value="integrations.trains_driver"
                        >
                            <option
                                v-for="option in driver_options.trains"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.trains_driver" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="trip_covers_driver"
                            >Trip cover driver</Label
                        >
                        <select
                            id="trip_covers_driver"
                            name="trip_covers_driver"
                            :class="selectClass"
                            :default-value="integrations.trip_covers_driver"
                        >
                            <option
                                v-for="option in driver_options.trip_covers"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.trip_covers_driver" />
                    </div>

                    <div class="flex items-center gap-3 md:col-span-2">
                        <input type="hidden" name="trip_covers_enabled" value="0" />
                        <input
                            id="trip_covers_enabled"
                            type="checkbox"
                            name="trip_covers_enabled"
                            value="1"
                            :class="checkboxClass"
                            :checked="integrations.trip_covers_enabled"
                        />
                        <Label for="trip_covers_enabled"
                            >Enable trip cover generation</Label
                        >
                    </div>

                    <div class="flex items-center gap-3 md:col-span-2">
                        <input
                            type="hidden"
                            name="trip_covers_use_gemini_prompt"
                            value="0"
                        />
                        <input
                            id="trip_covers_use_gemini_prompt"
                            type="checkbox"
                            name="trip_covers_use_gemini_prompt"
                            value="1"
                            :class="checkboxClass"
                            :checked="integrations.trip_covers_use_gemini_prompt"
                        />
                        <Label for="trip_covers_use_gemini_prompt"
                            >Enhance cover prompts with Gemini</Label
                        >
                    </div>

                    <div class="flex items-center gap-3 md:col-span-2">
                        <input
                            type="hidden"
                            name="trip_covers_pollinations_fallback"
                            value="0"
                        />
                        <input
                            id="trip_covers_pollinations_fallback"
                            type="checkbox"
                            name="trip_covers_pollinations_fallback"
                            value="1"
                            :class="checkboxClass"
                            :checked="
                                integrations.trip_covers_pollinations_fallback
                            "
                        />
                        <Label for="trip_covers_pollinations_fallback"
                            >Allow Pollinations fallback in cover ladder</Label
                        >
                    </div>

                    <div class="flex items-center gap-3 md:col-span-2">
                        <input type="hidden" name="gemini_image_enabled" value="0" />
                        <input
                            id="gemini_image_enabled"
                            type="checkbox"
                            name="gemini_image_enabled"
                            value="1"
                            :class="checkboxClass"
                            :checked="integrations.gemini_image_enabled"
                        />
                        <Label for="gemini_image_enabled"
                            >Enable Gemini image generation for covers</Label
                        >
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">API keys</CardTitle>
                    <CardDescription>
                        Leave a field blank to keep the current key. Keys are
                        encrypted in the database.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="geoapify_api_key">Geoapify API key</Label>
                        <Input
                            id="geoapify_api_key"
                            name="geoapify_api_key"
                            type="password"
                            autocomplete="off"
                            placeholder="••••••••"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                integrations.geoapify_api_key_configured
                                    ? 'Currently configured'
                                    : 'Not configured'
                            }}
                        </p>
                        <InputError :message="errors.geoapify_api_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gemini_api_key">Gemini API key</Label>
                        <Input
                            id="gemini_api_key"
                            name="gemini_api_key"
                            type="password"
                            autocomplete="off"
                            placeholder="••••••••"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                integrations.gemini_api_key_configured
                                    ? 'Currently configured'
                                    : 'Not configured'
                            }}
                        </p>
                        <InputError :message="errors.gemini_api_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="railradar_api_key">RailRadar API key</Label>
                        <Input
                            id="railradar_api_key"
                            name="railradar_api_key"
                            type="password"
                            autocomplete="off"
                            placeholder="••••••••"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                integrations.railradar_api_key_configured
                                    ? 'Currently configured'
                                    : 'Not configured'
                            }}
                        </p>
                        <InputError :message="errors.railradar_api_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="openweathermap_api_key"
                            >OpenWeatherMap API key</Label
                        >
                        <Input
                            id="openweathermap_api_key"
                            name="openweathermap_api_key"
                            type="password"
                            autocomplete="off"
                            placeholder="••••••••"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                integrations.openweathermap_api_key_configured
                                    ? 'Currently configured'
                                    : 'Not configured'
                            }}
                        </p>
                        <InputError :message="errors.openweathermap_api_key" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="unsplash_access_key"
                            >Unsplash access key</Label
                        >
                        <Input
                            id="unsplash_access_key"
                            name="unsplash_access_key"
                            type="password"
                            autocomplete="off"
                            placeholder="••••••••"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                integrations.unsplash_access_key_configured
                                    ? 'Currently configured'
                                    : 'Not configured'
                            }}
                        </p>
                        <InputError :message="errors.unsplash_access_key" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="text-base">Brand logo</CardTitle>
                    <CardDescription>
                        Choose the logo mark shown in the sidebar, auth pages,
                        and landing page.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <LogoOptionsShowcase
                        selectable
                        :selected="integrations.logo"
                    />
                    <InputError :message="errors.logo" class="mt-4" />
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="processing">
                    Save settings
                </Button>
            </div>
        </Form>
    </div>
</template>
