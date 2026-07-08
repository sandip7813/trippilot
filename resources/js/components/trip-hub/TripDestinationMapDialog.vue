<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { openStreetMapEmbedUrl } from '@/lib/maps';
import type { TripLocation } from '@/types/trip';
import { openStreetMapUrl } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    destination: TripLocation | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const hasCoordinates = computed(
    () => props.destination?.lat != null && props.destination?.lng != null,
);

const embedUrl = computed(() => {
    if (
        !hasCoordinates.value ||
        props.destination?.lat == null ||
        props.destination?.lng == null
    ) {
        return null;
    }

    return openStreetMapEmbedUrl(props.destination.lat, props.destination.lng);
});

const externalMapUrl = computed(() => {
    if (
        !hasCoordinates.value ||
        props.destination?.lat == null ||
        props.destination?.lng == null
    ) {
        return null;
    }

    return openStreetMapUrl(props.destination.lat, props.destination.lng);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="w-[min(96vw,72rem)] gap-0 overflow-hidden p-0 sm:max-w-6xl"
        >
            <DialogHeader class="space-y-1 border-b border-border/60 px-6 py-4">
                <DialogTitle>Destination map</DialogTitle>
                <DialogDescription>
                    {{ locationLabel(destination) ?? 'Map preview' }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="embedUrl"
                class="aspect-[16/9] min-h-[50vh] w-full bg-muted sm:min-h-[60vh]"
            >
                <iframe
                    :src="embedUrl"
                    :title="`Map of ${locationLabel(destination) ?? 'destination'}`"
                    class="size-full border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                />
            </div>

            <p v-else class="px-6 py-8 text-sm text-muted-foreground">
                Map preview is available when the destination has saved
                coordinates from search.
            </p>

            <div
                v-if="externalMapUrl"
                class="border-t border-border/60 px-6 py-3 text-sm"
            >
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
