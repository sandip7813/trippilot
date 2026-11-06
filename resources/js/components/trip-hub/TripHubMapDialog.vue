<script setup lang="ts">
import {
    Car,
    ExternalLink,
    Footprints,
    MapPin,
    Route,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    googleMapsDirectionsUrl,
    googleMapsEmbedDirectionsUrl,
    googleMapsPlaceUrl,
} from '@/lib/maps';
import type { MapTravelMode } from '@/lib/maps';
import { cn } from '@/lib/utils';

/**
 * Any point worth showing on a map: a hotel, a station, a taxi stand. With
 * an `origin`, the map shows the route from that point to the place instead
 * of a single pin.
 */
export type MapPlace = {
    name: string;
    /** Shown under the title; usually an address or a short description. */
    description?: string | null;
    lat: number | null;
    lng: number | null;
    origin?: { label: string; lat: number; lng: number } | null;
    /** Travel mode the route starts in; walking unless set. */
    travelMode?: MapTravelMode;
};

const props = defineProps<{
    place: MapPlace | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const DEFAULT_ZOOM = 16;
const MIN_ZOOM = 3;
const MAX_ZOOM = 21;

const travelModes: { value: MapTravelMode; label: string; icon: typeof Car }[] =
    [
        { value: 'walking', label: 'Walk', icon: Footprints },
        { value: 'driving', label: 'Drive', icon: Car },
    ];

const zoom = ref(DEFAULT_ZOOM);
const travelMode = ref<MapTravelMode>('walking');

watch(
    [open, () => props.place],
    () => {
        zoom.value = DEFAULT_ZOOM;
        travelMode.value = props.place?.travelMode ?? 'walking';
    },
    { immediate: true },
);

function changeZoom(step: number): void {
    zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom.value + step));
}

const destination = computed(() =>
    props.place?.lat != null && props.place.lng != null
        ? { lat: props.place.lat, lng: props.place.lng }
        : null,
);

const origin = computed(() => props.place?.origin ?? null);
const showsRoute = computed(
    () => origin.value !== null && destination.value !== null,
);

const embedUrl = computed(() => {
    if (!destination.value) {
        return null;
    }

    if (origin.value) {
        return googleMapsEmbedDirectionsUrl(
            origin.value,
            destination.value,
            travelMode.value,
        );
    }

    return `https://maps.google.com/maps?q=${destination.value.lat},${destination.value.lng}&z=${zoom.value}&output=embed`;
});

const externalUrl = computed(() => {
    if (!destination.value) {
        return null;
    }

    return origin.value
        ? googleMapsDirectionsUrl(
              origin.value,
              destination.value,
              travelMode.value,
          )
        : googleMapsPlaceUrl(destination.value.lat, destination.value.lng);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-7xl">
            <DialogHeader class="space-y-1 border-b border-border/60 px-6 py-4">
                <DialogTitle class="flex items-center gap-2 pr-6">
                    <component
                        :is="showsRoute ? Route : MapPin"
                        class="size-4 shrink-0 text-indigo-500"
                    />
                    {{ place?.name }}
                </DialogTitle>
                <DialogDescription>
                    {{ place?.description ?? 'Location on the map' }}
                </DialogDescription>

                <div
                    v-if="showsRoute && origin"
                    class="flex flex-wrap items-center justify-between gap-3 pt-2"
                >
                    <p class="text-sm text-muted-foreground">
                        Route from
                        <span class="font-medium text-foreground">
                            {{ origin.label }}
                        </span>
                        to
                        <span class="font-medium text-foreground">
                            {{ place?.name }}
                        </span>
                    </p>

                    <div
                        class="inline-flex rounded-lg bg-muted p-1"
                        role="group"
                        aria-label="Travel mode"
                    >
                        <button
                            v-for="mode in travelModes"
                            :key="mode.value"
                            type="button"
                            :aria-pressed="travelMode === mode.value"
                            :class="
                                cn(
                                    'inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-semibold transition-colors',
                                    travelMode === mode.value
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="travelMode = mode.value"
                        >
                            <component :is="mode.icon" class="size-3.5" />
                            {{ mode.label }}
                        </button>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="open && embedUrl" class="relative">
                <iframe
                    :key="embedUrl"
                    :src="embedUrl"
                    :title="
                        showsRoute
                            ? `Route to ${place?.name}`
                            : `Map showing ${place?.name}`
                    "
                    class="h-[70vh] min-h-[24rem] w-full border-0"
                    loading="lazy"
                    allowfullscreen
                    referrerpolicy="no-referrer-when-downgrade"
                />

                <div
                    v-if="!showsRoute"
                    class="absolute top-3 left-3 flex flex-col gap-1.5"
                    role="group"
                    aria-label="Map zoom"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="shadow-md"
                        aria-label="Zoom in"
                        :disabled="zoom >= MAX_ZOOM"
                        @click="changeZoom(1)"
                    >
                        <ZoomIn class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="shadow-md"
                        aria-label="Zoom out"
                        :disabled="zoom <= MIN_ZOOM"
                        @click="changeZoom(-1)"
                    >
                        <ZoomOut class="size-4" />
                    </Button>
                </div>
            </div>

            <div v-if="externalUrl" class="border-t border-border/60 px-6 py-3">
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="externalUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink class="size-4" />
                        {{
                            showsRoute
                                ? 'Open directions in Google Maps'
                                : 'Open in Google Maps'
                        }}
                    </a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
