<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Palette } from '@lucide/vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance settings</h1>

        <SettingsCard
            title="Appearance settings"
            description="Update the appearance settings for your account"
            :icon="Palette"
            accent="amber"
        >
            <AppearanceTabs />
        </SettingsCard>
    </div>
</template>
