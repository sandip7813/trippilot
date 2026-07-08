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
import { index as roadTripsIndex, show } from '@/routes/road-trips';
import type { RoadTrip, RoadTripFormOptions } from '@/types/roadTrip';

const { trip } = defineProps<
    RoadTripFormOptions & {
        trip: RoadTrip;
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Road Trips', href: roadTripsIndex() }],
    },
});
</script>

<template>
    <Head :title="`Edit ${trip.title}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Edit road trip"
            :description="`Update route details for ${trip.title}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="show(trip.id)">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to road trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Form
            v-bind="RoadTripController.update.form(trip.id)"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Saving road trip..."
            />

            <Card class="card-vibrant overflow-hidden">
                <div class="brand-gradient h-1.5" />
                <CardContent class="space-y-6 pt-6">
                    <RoadTripFormFields
                        :trip="trip"
                        :vehicle-classes="vehicleClasses"
                        :fuel-types="fuelTypes"
                        :driving-paces="drivingPaces"
                        :food-preferences="foodPreferences"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Save changes
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="show(trip.id)"> Cancel </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
