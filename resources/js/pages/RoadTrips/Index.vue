<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, MapPin, Plus, Route } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { tripCardAccent } from '@/lib/card-accents';
import { create, index as roadTripsIndex, show } from '@/routes/road-trips';
import { formatDrivingDistance, formatDrivingDuration } from '@/types/roadTrip';
import type { RoadTrip } from '@/types/roadTrip';
import { locationLabel, locationRouteLabel } from '@/types/trip';

defineProps<{
    trips: RoadTrip[];
}>();

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

function coverThumbUrl(trip: RoadTrip): string | null {
    return trip.cover_image_thumb_url ?? trip.cover_image_url ?? null;
}
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
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        New road trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="trips.length === 0"
            title="No road trips yet"
            description="Plot routes, add stops, and see drive times on interactive maps."
            :icon="Route"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        Plan your first drive
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="(trip, index) in trips"
                :key="trip.id"
                class="card-vibrant group !flex-row items-stretch gap-0 overflow-hidden !py-0"
            >
                <Link
                    v-if="coverThumbUrl(trip)"
                    :href="show(trip.id)"
                    class="relative w-[7.5rem] shrink-0 self-stretch overflow-hidden sm:w-32"
                >
                    <img
                        :src="coverThumbUrl(trip) ?? undefined"
                        :alt="`${trip.title} cover`"
                        width="384"
                        height="512"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                </Link>
                <div
                    v-else
                    class="w-1 shrink-0 bg-gradient-to-b"
                    :class="tripCardAccent(index)"
                />

                <div class="flex min-w-0 flex-1 flex-col justify-between p-4">
                    <div>
                        <h3
                            class="truncate text-base leading-tight font-semibold"
                        >
                            <Link
                                :href="show(trip.id)"
                                class="transition-colors hover:text-primary"
                            >
                                {{ trip.title }}
                            </Link>
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                locationRouteLabel(
                                    trip.origin,
                                    trip.destination,
                                )
                            }}
                        </p>
                        <p
                            v-if="
                                locationLabel(trip.destination) &&
                                !trip.origin?.label
                            "
                            class="mt-1 flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <MapPin
                                class="size-3.5 shrink-0 text-teal-600 dark:text-teal-400"
                            />
                            <span class="truncate">{{
                                locationLabel(trip.destination)
                            }}</span>
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="trip.route"
                            variant="secondary"
                            class="text-xs"
                        >
                            <Route class="mr-1 size-3" />
                            {{ formatDrivingDistance(trip.route.distance_km) }}
                        </Badge>
                        <Badge
                            v-if="trip.route"
                            variant="outline"
                            class="text-xs"
                        >
                            <Clock class="mr-1 size-3" />
                            {{
                                formatDrivingDuration(
                                    trip.route.duration_seconds,
                                )
                            }}
                        </Badge>
                        <Badge
                            v-if="trip.stops.length > 0"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ trip.stops.length }} stop{{
                                trip.stops.length === 1 ? '' : 's'
                            }}
                        </Badge>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
