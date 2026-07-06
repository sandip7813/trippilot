<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import PageHeader from '@/components/PageHeader.vue';
import TripFormFields from '@/components/TripFormFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create, index as tripsIndex } from '@/routes/trips';
import type { TripOption } from '@/types/trip';

defineProps<{
    tripTypes: TripOption[];
    tripStatuses: TripOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
            { title: 'New trip', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New Trip" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New trip"
            description="Start with the basics — you can build out the itinerary later."
        />

        <Form
            v-bind="TripController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <TripFormFields :trip-types="tripTypes" :errors="errors" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Create trip
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="tripsIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Cancel
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
