<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TripFormFields from '@/components/TripFormFields.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ArrowLeft, MapPin, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index as tripsIndex, show } from '@/routes/trips';
import type { Trip, TripOption } from '@/types/trip';
import { locationHasCoordinates } from '@/types/trip';

const { trip } = defineProps<{
    trip: Trip;
    tripTypes: TripOption[];
    tripStatuses: TripOption[];
    travelStyles: TripOption[];
}>();

const hasItinerary = computed(() => (trip.itinerary?.days?.length ?? 0) > 0);

const needsDestinationCoordinates = computed(
    () => Boolean(trip.destination?.label) && ! locationHasCoordinates(trip.destination),
);

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
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="show(trip.id)">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="hasItinerary">
            <TriangleAlert class="size-4" />
            <AlertTitle>AI itinerary will be removed</AlertTitle>
            <AlertDescription>
                Changing route, dates, travelers, or planning details clears your current
                itinerary. You can generate a fresh plan after saving.
            </AlertDescription>
        </Alert>

        <Alert v-if="needsDestinationCoordinates">
            <MapPin class="size-4" />
            <AlertTitle>Re-select destination from search</AlertTitle>
            <AlertDescription>
                Pick your destination from the dropdown below to enable weather, maps, and trip scope.
            </AlertDescription>
        </Alert>

        <Form
            v-bind="TripController.update.form(trip.id)"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Saving changes..."
            />

            <Card class="card-vibrant overflow-hidden">
                <div class="brand-gradient h-1.5" />
                <CardContent class="space-y-6 pt-6">
                    <TripFormFields
                        :trip="trip"
                        :trip-types="tripTypes"
                        :trip-statuses="tripStatuses"
                        :travel-styles="travelStyles"
                        :errors="errors"
                        show-status
                    />

                    <input type="hidden" name="is_favorite" :value="trip.is_favorite ? '1' : '0'" />
                </CardContent>
            </Card>

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
