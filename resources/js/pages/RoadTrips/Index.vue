<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as roadTripsIndex } from '@/routes/road-trips';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Road Trips',
                href: roadTripsIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Road Trips" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h1 class="text-2xl font-semibold">Road Trips</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Plan routes with maps, stops, and estimates. Road trip features
                arrive in Phase 5.
            </p>
        </div>
    </div>
</template>
