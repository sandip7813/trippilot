<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import PageHeader from '@/components/PageHeader.vue';
import TripFormFields from '@/components/TripFormFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index as tripsIndex, show } from '@/routes/trips';
import type { Trip, TripOption } from '@/types/trip';

const { trip } = defineProps<{
    trip: Trip;
    tripTypes: TripOption[];
    tripStatuses: TripOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${trip.title}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Edit trip"
            :description="`Update details for ${trip.title}`"
        />

        <Form
            v-bind="TripController.update.form(trip.id)"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <TripFormFields
                :trip="trip"
                :trip-types="tripTypes"
                :trip-statuses="tripStatuses"
                :errors="errors"
                show-status
            />

            <input type="hidden" name="is_favorite" :value="trip.is_favorite ? '1' : '0'" />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Save changes
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="show(trip.id)">
                        <ArrowLeft class="mr-2 size-4" />
                        Cancel
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
