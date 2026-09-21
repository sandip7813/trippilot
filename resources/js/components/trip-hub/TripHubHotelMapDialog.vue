<script setup lang="ts">
import { ExternalLink, MapPin, ZoomIn, ZoomOut } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TripHotel } from '@/types/hotel';

const props = defineProps<{
    hotel: TripHotel | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const DEFAULT_ZOOM = 16;
const MIN_ZOOM = 3;
const MAX_ZOOM = 21;

const zoom = ref(DEFAULT_ZOOM);

watch([open, () => props.hotel], () => {
    zoom.value = DEFAULT_ZOOM;
});

function changeZoom(step: number): void {
    zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom.value + step));
}

const position = computed(() =>
    props.hotel?.lat != null && props.hotel.lng != null
        ? `${props.hotel.lat},${props.hotel.lng}`
        : null,
);

const embedUrl = computed(() =>
    position.value
        ? `https://maps.google.com/maps?q=${position.value}&z=${zoom.value}&output=embed`
        : null,
);

const externalUrl = computed(() =>
    position.value
        ? `https://www.google.com/maps/search/?api=1&query=${position.value}`
        : null,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-7xl">
            <DialogHeader class="space-y-1 border-b border-border/60 px-6 py-4">
                <DialogTitle class="flex items-center gap-2 pr-6">
                    <MapPin class="size-4 shrink-0 text-indigo-500" />
                    {{ hotel?.name }}
                </DialogTitle>
                <DialogDescription>
                    {{ hotel?.address ?? 'Hotel location' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="open && embedUrl" class="relative">
                <iframe
                    :src="embedUrl"
                    :title="`Map showing ${hotel?.name}`"
                    class="h-[70vh] min-h-[24rem] w-full border-0"
                    loading="lazy"
                    allowfullscreen
                    referrerpolicy="no-referrer-when-downgrade"
                />

                <div
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
                        Open in Google Maps
                    </a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
