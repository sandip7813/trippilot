<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as tripsIndex } from '@/routes/trips';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Trips',
                href: tripsIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Trips" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h1 class="text-2xl font-semibold">Trips</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Plan and manage your vacation itineraries. Full trip CRUD
                arrives in Phase 1.
            </p>
        </div>
    </div>
</template>
