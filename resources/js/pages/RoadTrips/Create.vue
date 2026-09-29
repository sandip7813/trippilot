<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Megaphone } from '@lucide/vue';
import { ref } from 'vue';
import RoadTripController from '@/actions/App/Http/Controllers/RoadTripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import OpenTripDetailsFields from '@/components/OpenTripDetailsFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import RoadTripFormFields from '@/components/road-trips/RoadTripFormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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

const makeOpenTrip = ref(false);
const tripStartDate = ref('');
</script>

<template>
    <Head title="New Road Trip" />

    <div
        class="mx-auto flex w-full flex-1 flex-col gap-6 p-4 transition-[max-width] md:p-6"
        :class="makeOpenTrip ? 'max-w-5xl' : 'max-w-2xl'"
    >
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
                <CardContent class="pt-6">
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox
                            v-model="makeOpenTrip"
                            name="make_open_trip"
                            value="1"
                            class="mt-0.5"
                        />
                        <span>
                            <span class="font-medium text-foreground">
                                Make this an open trip
                            </span>
                            <span class="mt-1 block text-sm text-muted-foreground">
                                List it publicly so other travelers — bikers,
                                road-trippers — can discover it, contact you,
                                and request to join. Fill in the group details
                                that appear alongside the trip form below — you
                                can change or unpublish anytime.
                            </span>
                        </span>
                    </Label>
                </CardContent>
            </Card>

            <div class="grid gap-6" :class="makeOpenTrip ? 'lg:grid-cols-2' : ''">
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
                            v-model:start-date="tripStartDate"
                        />
                    </CardContent>
                </Card>

                <Card v-if="makeOpenTrip" class="card-vibrant overflow-hidden">
                    <div class="brand-gradient h-1.5" />
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Megaphone class="size-5" />
                            Open trip details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <OpenTripDetailsFields
                            name-prefix="open_trip"
                            :errors="errors"
                            :trip-start-date="tripStartDate"
                        />
                    </CardContent>
                </Card>
            </div>

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
