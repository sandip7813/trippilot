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
    <section class="space-y-3 pt-2">
        <h2
            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
        >
            Useful links
        </h2>
        <div class="flex flex-wrap gap-2 text-sm">
            <a
                v-if="mapUrl"
                :href="mapUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-card/80 px-3.5 py-2 font-medium text-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:text-primary hover:shadow-md"
            >
                <MapPin class="size-4 shrink-0 text-rose-500" />
                View destination on map
                <ExternalLink class="size-3.5 shrink-0 text-muted-foreground" />
            </a>
            <a
                v-if="trip.trip_scope === 'domestic'"
                href="https://www.irctc.co.in/"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-card/80 px-3.5 py-2 font-medium text-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:text-primary hover:shadow-md"
            >
                <ExternalLink class="size-4 shrink-0 text-orange-500" />
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
