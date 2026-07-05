<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
</script>

<template>
    <Head title="Super Admin Settings" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h1 class="text-2xl font-semibold">Super Admin Settings</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                System settings, integration drivers, and admin management will
                be configured here in Phase 6.
            </p>
        </div>
    </div>
</template>
