<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPinned, Plus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
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

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Trips"
            description="Plan and manage your vacation itineraries."
            :icon="MapPinned"
        >
            <template #actions>
                <Button disabled>
                    <Plus class="mr-2 size-4" />
                    New trip
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            title="No trips yet"
            description="Your vacation itineraries will appear here. Full trip creation and editing arrives in Phase 1 — you'll be able to build day-by-day plans with AI assistance."
            :icon="MapPinned"
        />
    </div>
</template>
