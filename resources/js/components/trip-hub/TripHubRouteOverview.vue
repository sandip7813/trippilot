<script setup lang="ts">
import { Maximize2, Navigation } from '@lucide/vue';
import { computed, defineAsyncComponent, ref } from 'vue';
import TripHubRouteStopsList from '@/components/trip-hub/TripHubRouteStopsList.vue';
import TripRouteMapDialog from '@/components/trip-hub/TripRouteMapDialog.vue';
import { Button } from '@/components/ui/button';
import {
    shortCityLabel,
    useTripRouteStops,
} from '@/composables/useTripRouteStops';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const TripRouteMap = defineAsyncComponent(
    () => import('@/components/trip-hub/TripRouteMap.vue'),
);

const props = defineProps<{
    trip: Trip;
}>();

const mapOpen = ref(false);

const { routeChainLabels, routeStops, routeMapPoints } = useTripRouteStops({
    trip: props.trip,
    routeSummary: props.trip.route_summary,
});

const showFullRoute = computed(() => routeStops.value.length >= 2);
const canShowMap = computed(() => routeMapPoints.value.length > 0);
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border/60 bg-card/80 shadow-sm"
    >
        <!--
            `isolate` keeps Leaflet's layers (z-index 200–1000) inside this
            box, so they can't show through dialogs opened above the page.
        -->
        <div
            v-if="canShowMap"
            class="relative isolate h-64 border-b border-border/60 sm:h-72"
        >
            <TripRouteMap :points="routeMapPoints" compact />

            <Button
                type="button"
                variant="secondary"
                size="sm"
                class="absolute top-3 right-3 z-[1000] shadow-md"
                @click="mapOpen = true"
            >
                <Maximize2 class="size-3.5" />
                Larger map
            </Button>
        </div>

        <div class="space-y-3 p-4 text-sm">
            <template v-if="showFullRoute">
                <div
                    v-if="routeChainLabels.length > 0"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <template
                        v-for="(label, index) in routeChainLabels"
                        :key="`route-chain-${index}-${label}`"
                    >
                        <span
                            class="rounded-full bg-teal-500/10 px-2.5 py-0.5 text-xs font-medium text-teal-700 dark:text-teal-300"
                        >
                            {{ shortCityLabel(label) }}
                        </span>
                        <span
                            v-if="index < routeChainLabels.length - 1"
                            class="text-xs text-muted-foreground"
                            aria-hidden="true"
                        >
                            →
                        </span>
                    </template>
                </div>

                <TripHubRouteStopsList :stops="routeStops" />
            </template>

            <div v-else class="flex gap-3">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-md bg-teal-500/15 text-teal-600 dark:text-teal-400"
                >
                    <Navigation class="size-4" />
                </span>
                <div class="space-y-0.5 leading-snug">
                    <p v-if="locationLabel(trip.origin)">
                        <span class="text-muted-foreground">From</span>
                        {{ locationLabel(trip.origin) }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">To</span>
                        {{ locationLabel(trip.destination) ?? 'Not set' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <TripRouteMapDialog v-model:open="mapOpen" :trip="trip" />
</template>
