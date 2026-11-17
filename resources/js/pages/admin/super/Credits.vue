<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import CreditPackController from '@/actions/App/Http/Controllers/Admin/Super/CreditPackController';
import CreditSettingsController from '@/actions/App/Http/Controllers/Admin/Super/CreditSettingsController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
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
import { index as creditPurchasesIndex } from '@/routes/admin/super/credit-purchases';
import { show as creditsShow } from '@/routes/admin/super/credits';
import type { CreditServiceKey } from '@/types/credits';

defineProps<{
    enabled: boolean;
    signup_bonus: number;
    services: {
        service: CreditServiceKey;
        label: string;
        credits: number;
        default_credits: number;
    }[];
    payments_configured: boolean;
    packs: {
        id: number;
        name: string;
        price: number;
        credits: number;
        is_featured: boolean;
        is_active: boolean;
    }[];
}>();

const checkboxClass =
    'size-4 rounded border border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Super Admin', href: superSettings() },
            { title: 'Credits Settings', href: creditsShow() },
        ],
    },
});
</script>

<template>
    <Head title="Credits Settings" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Credits Settings"
            description="Set how many credits each AI service costs. Admins and super admins never spend credits."
            :icon="Coins"
        />

        <Form
            v-bind="CreditSettingsController.update.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
            :options="{ preserveScroll: true }"
        >
            <FormSavingOverlay :show="processing" message="Saving..." />

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle>Credit charging</CardTitle>
                        <Badge :variant="enabled ? 'default' : 'outline'">
                            {{ enabled ? 'On' : 'Off' }}
                        </Badge>
                    </div>
                    <CardDescription>
                        While charging is off, everyone uses AI features for
                        free and no credits are taken. New users still receive
                        the {{ signup_bonus }}-credit welcome bonus when they
                        verify their email.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="enabled" value="0" />
                        <input
                            id="credits_enabled"
                            type="checkbox"
                            name="enabled"
                            value="1"
                            :class="checkboxClass"
                            :checked="enabled"
                        />
                        <Label for="credits_enabled">
                            Charge credits for AI features
                        </Label>
                    </div>
                    <InputError :message="errors.enabled" class="mt-2" />
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle>Credit cost per service</CardTitle>
                    <CardDescription>
                        Changes apply to future AI requests only. Users see
                        these costs in Settings → Credits and on each AI button.
                        Set 0 to make a service free.
                    </CardDescription>
                </CardHeader>
                <CardContent class="divide-y divide-border/60 p-0">
                    <div
                        v-for="item in services"
                        :key="item.service"
                        class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <Label :for="`cost-${item.service}`">
                                {{ item.label }}
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Default: {{ item.default_credits }} credits
                            </p>
                            <InputError
                                :message="errors[`costs.${item.service}`]"
                                class="mt-1"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <Input
                                :id="`cost-${item.service}`"
                                :name="`costs[${item.service}]`"
                                type="number"
                                min="0"
                                max="1000"
                                step="1"
                                required
                                :default-value="item.credits"
                                class="w-28 text-right"
                            />
                            <span class="text-sm text-muted-foreground">
                                credits
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="processing">
                    Save credit settings
                </Button>
            </div>
        </Form>

        <Form
            v-bind="CreditPackController.update.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
            :options="{ preserveScroll: true }"
        >
            <FormSavingOverlay :show="processing" message="Saving packs..." />

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle>Credit packs</CardTitle>
                        <Badge
                            :variant="
                                payments_configured ? 'default' : 'outline'
                            "
                        >
                            {{
                                payments_configured
                                    ? 'Razorpay connected'
                                    : 'Razorpay not set up'
                            }}
                        </Badge>
                    </div>
                    <CardDescription>
                        Packs users can buy in Settings → Credits while charging
                        is on. Prices are in rupees. Changes apply to new
                        purchases only.
                        <Link
                            :href="creditPurchasesIndex()"
                            class="font-medium underline"
                        >
                            View purchases
                        </Link>
                        <template v-if="!payments_configured">
                            Add the Razorpay keys in
                            <Link
                                :href="superSettings()"
                                class="font-medium underline"
                            >
                                Settings
                            </Link>
                            to start selling.
                        </template>
                    </CardDescription>
                </CardHeader>
                <CardContent class="divide-y divide-border/60 p-0">
                    <div
                        v-for="pack in packs"
                        :key="pack.id"
                        class="grid gap-3 px-6 py-4 sm:grid-cols-[1fr_8rem_8rem] sm:items-end"
                    >
                        <div class="grid gap-1.5">
                            <Label :for="`pack-${pack.id}-name`">Name</Label>
                            <Input
                                :id="`pack-${pack.id}-name`"
                                :name="`packs[${pack.id}][name]`"
                                :default-value="pack.name"
                                maxlength="64"
                                required
                            />
                            <InputError
                                :message="errors[`packs.${pack.id}.name`]"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`pack-${pack.id}-price`">
                                Price (₹)
                            </Label>
                            <Input
                                :id="`pack-${pack.id}-price`"
                                :name="`packs[${pack.id}][price]`"
                                type="number"
                                min="1"
                                step="1"
                                required
                                :default-value="pack.price"
                                class="text-right"
                            />
                            <InputError
                                :message="errors[`packs.${pack.id}.price`]"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`pack-${pack.id}-credits`">
                                Credits
                            </Label>
                            <Input
                                :id="`pack-${pack.id}-credits`"
                                :name="`packs[${pack.id}][credits]`"
                                type="number"
                                min="1"
                                step="1"
                                required
                                :default-value="pack.credits"
                                class="text-right"
                            />
                            <InputError
                                :message="errors[`packs.${pack.id}.credits`]"
                            />
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-6 gap-y-2 sm:col-span-3"
                        >
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    :name="`packs[${pack.id}][is_active]`"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    :name="`packs[${pack.id}][is_active]`"
                                    value="1"
                                    :class="checkboxClass"
                                    :checked="pack.is_active"
                                />
                                On sale
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    :name="`packs[${pack.id}][is_featured]`"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    :name="`packs[${pack.id}][is_featured]`"
                                    value="1"
                                    :class="checkboxClass"
                                    :checked="pack.is_featured"
                                />
                                Highlight as most popular
                            </label>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="processing">
                    Save credit packs
                </Button>
            </div>
        </Form>
    </div>
</template>
