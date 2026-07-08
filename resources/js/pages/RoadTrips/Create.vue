<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import RoadTripController from '@/actions/App/Http/Controllers/RoadTripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import PageHeader from '@/components/PageHeader.vue';
import RoadTripFormFields from '@/components/road-trips/RoadTripFormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { create, index as roadTripsIndex } from '@/routes/road-trips';
import type { RoadTripFormOptions } from '@/types/roadTrip';
import type { TripLocation } from '@/types/trip';

defineProps<
    RoadTripFormOptions & {
        defaultOrigin: TripLocation | null;
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Road Trips', href: roadTripsIndex() },
            { title: 'New road trip', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New Road Trip" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New road trip"
            description="Plot your route, set your vehicle, and we'll calculate drive time and map the journey."
        />

        <Form
            v-bind="RoadTripController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Creating road trip and calculating route..."
            />

            <Card class="card-vibrant overflow-hidden">
                <div class="brand-gradient h-1.5" />
                <CardContent class="space-y-6 pt-6">
                    <RoadTripFormFields
                        :vehicle-classes="vehicleClasses"
                        :fuel-types="fuelTypes"
                        :driving-paces="drivingPaces"
                        :food-preferences="foodPreferences"
                        :default-origin="defaultOrigin"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Create road trip
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="roadTripsIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Cancel
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
