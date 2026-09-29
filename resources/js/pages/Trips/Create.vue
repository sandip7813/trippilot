<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Megaphone } from '@lucide/vue';
import { ref } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import OpenTripDetailsFields from '@/components/OpenTripDetailsFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import TripFormFields from '@/components/TripFormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index as tripsIndex } from '@/routes/trips';
import type { TripLocation, TripOption } from '@/types/trip';

defineProps<{
    tripTypes: TripOption[];
    tripStatuses: TripOption[];
    travelStyles: TripOption[];
    defaultOrigin: TripLocation | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
            { title: 'New trip', href: create() },
        ],
    },
});

const makeOpenTrip = ref(false);
const tripStartDate = ref('');
</script>

<template>
    <Head title="New Trip" />

    <div
        class="mx-auto flex w-full flex-1 flex-col gap-6 p-4 transition-[max-width] md:p-6"
        :class="makeOpenTrip ? 'max-w-5xl' : 'max-w-2xl'"
    >
        <PageHeader
            title="New trip"
            description="Tell us where you're going — origin, destination, and travel style."
        />

        <Form
            v-bind="TripController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay :show="processing" message="Creating trip..." />

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
                                List it publicly so other travelers can discover
                                it, contact you, and request to join. Fill in the
                                group details that appear alongside the trip
                                form below — you can change or unpublish anytime.
                            </span>
                        </span>
                    </Label>
                </CardContent>
            </Card>

            <div class="grid gap-6" :class="makeOpenTrip ? 'lg:grid-cols-2' : ''">
                <Card class="card-vibrant overflow-hidden">
                    <div class="brand-gradient h-1.5" />
                    <CardContent class="space-y-6 pt-6">
                        <TripFormFields
                            :trip-types="tripTypes"
                            :travel-styles="travelStyles"
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
