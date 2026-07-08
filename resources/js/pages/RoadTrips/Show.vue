<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Clock, MapPin, Pencil, Route, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoadTripController from '@/actions/App/Http/Controllers/RoadTripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import RoadTripMap from '@/components/road-trips/RoadTripMap.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { edit, index as roadTripsIndex } from '@/routes/road-trips';
import {
    amenityLayerLabels,
    breakKindLabel,
    formatDrivingDistance,
    formatDrivingDuration,
} from '@/types/roadTrip';
import type { RoadTrip, RoadTripFormOptions } from '@/types/roadTrip';
import { locationRouteLabel } from '@/types/trip';

const props = defineProps<
    RoadTripFormOptions & {
        trip: RoadTrip;
        mapsConfigured: boolean;
        aiConfigured: boolean;
        amenityLayers: string[];
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Road Trips', href: roadTripsIndex() }],
    },
});

const page = usePage();

const activeAmenityLayer = ref<string | null>(null);

const errors = computed(
    () => (page.props.errors as Record<string, string> | undefined) ?? {},
);

const hasRoute = computed(() => (props.trip.route?.polyline?.length ?? 0) >= 2);

const activeAmenityPlaces = computed(() => {
    if (!activeAmenityLayer.value || !props.trip.amenities_cache) {
        return [];
    }

    return props.trip.amenities_cache[activeAmenityLayer.value]?.places ?? [];
});

function amenityCount(layer: string): number {
    return props.trip.amenities_cache?.[layer]?.places.length ?? 0;
}

function toggleAmenityLayer(layer: string): void {
    activeAmenityLayer.value =
        activeAmenityLayer.value === layer ? null : layer;
}
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="trip.title"
            :description="locationRouteLabel(trip.origin, trip.destination)"
            :icon="Route"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="roadTripsIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to road trips
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="edit(trip.id)">
                        <Pencil class="mr-2 size-4" />
                        Edit
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="!mapsConfigured">
            <MapPin class="size-4" />
            <AlertTitle>Maps not configured</AlertTitle>
            <AlertDescription>
                Add GEOAPIFY_API_KEY to calculate routes and load amenities
                along your drive.
            </AlertDescription>
        </Alert>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <Card class="card-vibrant overflow-hidden">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Route map</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <RoadTripMap
                        :origin="trip.origin"
                        :destination="trip.destination"
                        :route="trip.route"
                        :stops="trip.stops"
                        :suggested-breaks="trip.suggested_breaks"
                        :amenity-places="activeAmenityPlaces"
                        amenity-color="#6366f1"
                    />

                    <div
                        v-if="hasRoute"
                        class="flex flex-wrap gap-4 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Route class="size-4 text-teal-600" />
                            {{ formatDrivingDistance(trip.route!.distance_km) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Clock class="size-4 text-teal-600" />
                            {{
                                formatDrivingDuration(
                                    trip.route!.duration_seconds,
                                )
                            }}
                        </span>
                        <Badge v-if="trip.route!.has_tolls" variant="outline">
                            Tolls on route
                        </Badge>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Route not calculated yet. Recalculate after saving
                        origin and destination.
                    </p>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Route actions</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Form
                            v-bind="
                                RoadTripController.computeRoute.form(trip.id)
                            "
                            v-slot="{ processing }"
                            class="relative"
                        >
                            <FormSavingOverlay
                                :show="processing"
                                message="Recalculating route..."
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                class="w-full"
                                :disabled="processing || !mapsConfigured"
                            >
                                <Spinner v-if="processing" class="mr-2" />
                                Recalculate route
                            </Button>
                        </Form>

                        <Form
                            v-bind="
                                RoadTripController.suggestBreaks.form(trip.id)
                            "
                            v-slot="{ processing }"
                            class="relative"
                        >
                            <FormSavingOverlay
                                :show="processing"
                                message="Finding break suggestions along your route..."
                            />
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="
                                    processing || !hasRoute || !aiConfigured
                                "
                            >
                                <Spinner v-if="processing" class="mr-2" />
                                <Sparkles v-else class="mr-2 size-4" />
                                Suggest breaks with AI
                            </Button>
                        </Form>

                        <InputError :message="errors.route" />
                        <InputError :message="errors.ai" />
                    </CardContent>
                </Card>

                <Card v-if="hasRoute">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base"
                            >Amenities along route</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div
                            v-for="layer in amenityLayers"
                            :key="layer"
                            class="flex items-center gap-2"
                        >
                            <Form
                                v-bind="
                                    RoadTripController.amenities.form(trip.id, {
                                        query: { layer },
                                    })
                                "
                                v-slot="{ processing }"
                                class="flex-1"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start"
                                    :disabled="processing || !mapsConfigured"
                                >
                                    <Spinner v-if="processing" class="mr-2" />
                                    {{ amenityLayerLabels[layer] ?? layer }}
                                    <span
                                        v-if="amenityCount(layer) > 0"
                                        class="ml-auto text-xs text-muted-foreground"
                                    >
                                        {{ amenityCount(layer) }}
                                    </span>
                                </Button>
                            </Form>
                            <Button
                                v-if="amenityCount(layer) > 0"
                                type="button"
                                size="sm"
                                :variant="
                                    activeAmenityLayer === layer
                                        ? 'default'
                                        : 'ghost'
                                "
                                @click="toggleAmenityLayer(layer)"
                            >
                                Map
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Card v-if="trip.suggested_breaks.length > 0">
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Suggested breaks</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="breakItem in trip.suggested_breaks"
                    :key="breakItem.id"
                    class="flex flex-col gap-3 rounded-lg border border-border/60 p-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{
                                breakItem.title
                            }}</span>
                            <Badge variant="secondary">
                                {{ breakKindLabel(breakItem.kind) }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ breakItem.reason }}
                        </p>
                        <p
                            v-if="breakItem.address"
                            class="text-xs text-muted-foreground"
                        >
                            {{ breakItem.address }}
                        </p>
                    </div>

                    <Form
                        v-bind="RoadTripController.acceptBreak.form(trip.id)"
                        v-slot="{ processing }"
                    >
                        <input
                            type="hidden"
                            name="break_id"
                            :value="breakItem.id"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="mr-2" />
                            Add as stop
                        </Button>
                    </Form>
                </div>
            </CardContent>
        </Card>

        <Card v-if="trip.stops.length > 0">
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Your stops</CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="space-y-2">
                    <li
                        v-for="(stop, index) in trip.stops"
                        :key="`${stop.label}-${index}`"
                        class="flex items-start gap-2 text-sm"
                    >
                        <MapPin class="mt-0.5 size-4 shrink-0 text-teal-600" />
                        <div>
                            <span class="font-medium">{{ stop.label }}</span>
                            <p v-if="stop.notes" class="text-muted-foreground">
                                {{ stop.notes }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
