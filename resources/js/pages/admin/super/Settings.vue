<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Settings, ShieldCheck } from '@lucide/vue';
import LogoOptionsShowcase from '@/components/LogoOptionsShowcase.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { settings as superSettings } from '@/routes/admin/super';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Super Admin',
                href: superSettings(),
            },
            {
                title: 'Settings',
                href: superSettings(),
            },
        ],
    },
});

const integrationItems = [
    {
        name: 'Geoapify',
        description: 'Maps, geocoding, and routing',
        status: 'Configured',
    },
    {
        name: 'Google Gemini',
        description: 'AI itinerary and chat generation',
        status: 'Configured',
    },
    {
        name: 'Open-Meteo',
        description: 'Weather forecast and seasonal climate data (no API key)',
        status: 'Configured',
    },
];
</script>

<template>
    <Head title="Super Admin Settings" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Super Admin Settings"
            description="System configuration, integrations, and platform controls."
            :icon="ShieldCheck"
        />

        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Settings class="size-4" />
                    Integrations
                </CardTitle>
                <CardDescription>
                    External service drivers registered via
                    IntegrationServiceProvider. Full configuration UI arrives in
                    Phase 6.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="divide-y divide-border/60">
                    <li
                        v-for="item in integrationItems"
                        :key="item.name"
                        class="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="text-sm font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            {{ item.status }}
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="text-base">Brand logo</CardTitle>
                <CardDescription>
                    All logos are transparent SVGs. Preview options below and
                    pick your favourite.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <LogoOptionsShowcase />
            </CardContent>
        </Card>

        <Card
            class="border-dashed border-sidebar-border/70 dark:border-sidebar-border"
        >
            <CardContent class="py-10 text-center">
                <ShieldCheck class="mx-auto size-10 text-muted-foreground/50" />
                <p class="mt-4 text-sm font-medium">
                    Advanced settings coming soon
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Admin management, driver switching, and audit logs will be
                    added in Phase 6.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
