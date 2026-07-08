<script setup lang="ts">
import { ExternalLink, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type { Trip } from '@/types/trip';
import { locationHasCoordinates, openStreetMapUrl } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
}>();

const mapUrl = computed(() => {
    const destination = props.trip.destination;

    if (
        !locationHasCoordinates(destination) ||
        destination?.lat == null ||
        destination?.lng == null
    ) {
        return null;
    }

    return openStreetMapUrl(destination.lat, destination.lng);
});
</script>

<template>
    <section class="space-y-3 border-t border-border/60 pt-6">
        <h2 class="text-sm font-medium text-muted-foreground">Useful links</h2>
        <div
            class="flex flex-col gap-3 text-sm sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-2"
        >
            <a
                v-if="mapUrl"
                :href="mapUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 text-primary hover:underline"
            >
                <MapPin class="size-4 shrink-0" />
                View destination on OpenStreetMap
                <ExternalLink class="size-3.5 shrink-0" />
            </a>
            <a
                v-if="trip.trip_scope === 'domestic'"
                href="https://www.irctc.co.in/"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 text-primary hover:underline"
            >
                <ExternalLink class="size-4 shrink-0" />
                IRCTC — Indian Railways (information only)
            </a>
            <p
                v-if="!mapUrl && trip.trip_scope !== 'domestic'"
                class="text-muted-foreground"
            >
                Map links appear when your destination has saved coordinates.
            </p>
        </div>
    </section>
</template>
