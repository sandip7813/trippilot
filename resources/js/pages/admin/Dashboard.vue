<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard as adminDashboard } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: adminDashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Users</p>
                <p class="mt-1 text-2xl font-semibold">—</p>
            </div>
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Trips</p>
                <p class="mt-1 text-2xl font-semibold">—</p>
            </div>
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">AI Requests</p>
                <p class="mt-1 text-2xl font-semibold">—</p>
            </div>
        </div>
        <div
            class="relative min-h-[40vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
            <div class="relative z-10 p-6">
                <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
                <p class="mt-2 max-w-xl text-sm text-muted-foreground">
                    Platform overview for admins and super admins. Stats and
                    management tools will be added in Phase 6.
                </p>
            </div>
        </div>
    </div>
</template>
