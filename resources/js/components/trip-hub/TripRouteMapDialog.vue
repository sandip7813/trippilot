<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    shortCityLabel,
    useTripRouteStops,
} from '@/composables/useTripRouteStops';
import { openStreetMapBoundsUrl } from '@/types/trip';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const TripRouteMap = defineAsyncComponent(
    () => import('@/components/trip-hub/TripRouteMap.vue'),
);

const props = defineProps<{
    trip: Trip;
}>();

const open = defineModel<boolean>('open', { default: false });

const { routeChainLabels, routeStops, routeMapPoints } = useTripRouteStops({
    trip: props.trip,
    routeSummary: props.trip.route_summary,
});

const mappedStopCount = computed(() => routeMapPoints.value.length);

const totalStopCount = computed(() => routeStops.value.length);

const missingCoordinateStops = computed(() =>
    routeStops.value.filter(
        (stop) =>
            !routeMapPoints.value.some(
                (point) =>
                    point.sequence === stop.sequence &&
                    point.label === stop.label,
            ),
    ),
);

const externalMapUrl = computed(() =>
    openStreetMapBoundsUrl(routeMapPoints.value),
);

const dialogDescription = computed(() => {
    if (routeChainLabels.value.length > 0) {
        return routeChainLabels.value.map(shortCityLabel).join(' → ');
    }

    return (
        locationLabel(props.trip.origin) ??
        locationLabel(props.trip.destination) ??
        'Route preview'
    );
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="w-[min(96vw,72rem)] gap-0 overflow-hidden p-0 sm:max-w-6xl"
        >
            <DialogHeader class="space-y-1 border-b border-border/60 px-6 py-4">
                <DialogTitle>Route map</DialogTitle>
                <DialogDescription>
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="mappedStopCount > 0"
                class="aspect-[16/9] w-full overflow-hidden"
            >
                <TripRouteMap :points="routeMapPoints" />
            </div>

            <p v-else class="px-6 py-8 text-sm text-muted-foreground">
                Map preview needs coordinates from location search. Edit the
                trip and pick each city from search so pins can be placed.
            </p>

            <div
                v-if="missingCoordinateStops.length > 0 && mappedStopCount > 0"
                class="border-t border-border/60 px-6 py-3 text-xs text-muted-foreground"
            >
                Missing map pins for:
                {{
                    missingCoordinateStops
                        .map((stop) => shortCityLabel(stop.label))
                        .join(', ')
                }}
            </div>

            <div
                v-if="externalMapUrl"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 px-6 py-3 text-sm"
            >
                <span class="text-xs text-muted-foreground">
                    {{ mappedStopCount }} of {{ totalStopCount }} stops on map
                </span>
                <a
                    :href="externalMapUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary hover:underline"
                >
                    Open full map on OpenStreetMap
                </a>
            </div>
        </DialogContent>
    </Dialog>
</template>
