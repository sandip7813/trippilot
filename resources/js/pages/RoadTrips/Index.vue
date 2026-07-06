<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Route } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
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

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Road Trips"
            description="Plan scenic drives with maps, stops, and route estimates."
            :icon="Route"
        >
            <template #actions>
                <Button disabled>
                    <Plus class="mr-2 size-4" />
                    New road trip
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            title="No road trips yet"
            description="Plot routes, add stops, and see drive times on interactive maps. Road trip planning arrives in Phase 5 with Geoapify integration."
            :icon="Route"
        />
    </div>
</template>
