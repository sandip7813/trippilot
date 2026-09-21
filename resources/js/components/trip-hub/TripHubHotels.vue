<script setup lang="ts">
import {
    BedDouble,
    Clock,
    ExternalLink,
    Mail,
    MapPin,
    Phone,
    Star,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import TripHubHotelMapDialog from '@/components/trip-hub/TripHubHotelMapDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { TripHotel, TripHotels, TripHotelsPage } from '@/types/hotel';

type LocationState = {
    label: string;
    hotels: TripHotel[];
    loaded: boolean;
    hasMore: boolean;
    nextOffset: number;
    loading: boolean;
    error: string | null;
};

const props = defineProps<{
    tripId: string;
    hotels?: TripHotels | null;
}>();

const states = ref<LocationState[]>([]);
const activeIndex = ref(0);
const selectedHotel = ref<TripHotel | null>(null);
const mapOpen = ref(false);

watch(
    () => props.hotels,
    (hotels) => {
        states.value = (hotels?.locations ?? []).map((location) => ({
            label: location.label,
            hotels: location.hotels ?? [],
            loaded: location.hotels !== null && location.available,
            hasMore: location.has_more,
            nextOffset: location.next_offset,
            loading: false,
            error: location.available ? null : (location.message ?? null),
        }));
        activeIndex.value = 0;
    },
    { immediate: true },
);

const isLoading = computed(() => props.hotels === undefined);
const activeState = computed(() => states.value[activeIndex.value] ?? null);
const hasTabs = computed(() => states.value.length > 1);

async function loadPage(index: number): Promise<void> {
    const state = states.value[index];

    if (!state || state.loading) {
        return;
    }

    state.loading = true;
    state.error = null;

    try {
        const response = await fetch(
            TripController.hotels.url(
                { trip: props.tripId },
                { query: { location: index, offset: state.nextOffset } },
            ),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error('Could not load hotels.');
        }

        const page = (await response.json()) as TripHotelsPage;

        if (!page.available) {
            state.error = page.message ?? 'Could not load hotels.';

            return;
        }

        state.hotels.push(...(page.hotels ?? []));
        state.hasMore = page.has_more;
        state.nextOffset = page.next_offset;
        state.loaded = true;
    } catch {
        state.error = 'Could not load hotels. Please try again.';
    } finally {
        state.loading = false;
    }
}

function selectLocation(index: number): void {
    activeIndex.value = index;

    const state = states.value[index];

    if (state && !state.loaded && !state.error) {
        void loadPage(index);
    }
}

function showMap(hotel: TripHotel): void {
    selectedHotel.value = hotel;
    mapOpen.value = true;
}

function formatDistance(meters: number | null): string | null {
    if (meters === null) {
        return null;
    }

    return meters < 1000
        ? `${meters} m from centre`
        : `${(meters / 1000).toFixed(1)} km from centre`;
}

function websiteHost(url: string): string {
    try {
        return new URL(url).host.replace(/^www\./, '');
    } catch {
        return url;
    }
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div
            class="h-1.5 bg-gradient-to-r from-sky-400 via-indigo-500 to-violet-500"
        />
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-lg font-bold">
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400"
                >
                    <BedDouble class="size-4" />
                </span>
                Hotels
            </CardTitle>
            <p
                v-if="activeState && !hasTabs"
                class="text-sm text-muted-foreground"
            >
                Near {{ activeState.label }}
            </p>

            <div
                v-if="hasTabs"
                class="flex gap-1 overflow-x-auto pt-2"
                role="tablist"
            >
                <button
                    v-for="(state, index) in states"
                    :key="index"
                    type="button"
                    role="tab"
                    :aria-selected="activeIndex === index"
                    :class="
                        cn(
                            'shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                            activeIndex === index
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                        )
                    "
                    @click="selectLocation(index)"
                >
                    {{ state.label }}
                </button>
            </div>
        </CardHeader>

        <CardContent>
            <div v-if="isLoading" class="space-y-3">
                <Skeleton v-for="n in 4" :key="n" class="h-16 w-full" />
            </div>

            <template v-else-if="activeState">
                <ul
                    v-if="activeState.hotels.length > 0"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <li
                        v-for="hotel in activeState.hotels"
                        :key="hotel.place_id ?? hotel.name"
                        class="flex items-start justify-between gap-3 rounded-xl border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="min-w-0 space-y-1.5 break-words">
                            <p
                                class="flex flex-wrap items-center gap-x-2 font-semibold"
                            >
                                {{ hotel.name }}
                                <span
                                    v-if="hotel.stars"
                                    class="inline-flex items-center gap-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                >
                                    <Star class="size-3 fill-current" />
                                    {{ hotel.stars }}-star
                                </span>
                            </p>

                            <p
                                v-if="hotel.address"
                                class="flex items-start gap-1.5 text-sm text-muted-foreground"
                            >
                                <MapPin class="mt-0.5 size-3.5 shrink-0" />
                                {{ hotel.address }}
                            </p>

                            <div
                                v-if="
                                    formatDistance(hotel.distance_meters) ||
                                    hotel.rooms ||
                                    hotel.facilities.length
                                "
                                class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="formatDistance(hotel.distance_meters)"
                                >
                                    {{ formatDistance(hotel.distance_meters) }}
                                </span>
                                <span v-if="hotel.rooms">
                                    · {{ hotel.rooms }} rooms
                                </span>
                                <Badge
                                    v-for="facility in hotel.facilities"
                                    :key="facility"
                                    variant="outline"
                                    class="font-normal"
                                >
                                    {{ facility }}
                                </Badge>
                            </div>

                            <div
                                v-if="
                                    hotel.phone ||
                                    hotel.email ||
                                    hotel.website ||
                                    hotel.opening_hours
                                "
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                            >
                                <a
                                    v-if="hotel.phone"
                                    :href="`tel:${hotel.phone.replace(/\s+/g, '')}`"
                                    class="inline-flex items-center gap-1.5 text-primary hover:underline"
                                >
                                    <Phone class="size-3.5" />
                                    {{ hotel.phone }}
                                </a>
                                <a
                                    v-if="hotel.email"
                                    :href="`mailto:${hotel.email}`"
                                    class="inline-flex items-center gap-1.5 text-primary hover:underline"
                                >
                                    <Mail class="size-3.5" />
                                    {{ hotel.email }}
                                </a>
                                <a
                                    v-if="hotel.website"
                                    :href="hotel.website"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 text-primary hover:underline"
                                >
                                    <ExternalLink class="size-3.5" />
                                    {{ websiteHost(hotel.website) }}
                                </a>
                                <span
                                    v-if="hotel.opening_hours"
                                    class="inline-flex items-center gap-1.5 text-muted-foreground"
                                >
                                    <Clock class="size-3.5" />
                                    {{ hotel.opening_hours }}
                                </span>
                            </div>
                        </div>

                        <Button
                            v-if="hotel.lat !== null && hotel.lng !== null"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="showMap(hotel)"
                        >
                            <MapPin class="size-4" />
                            Map
                        </Button>
                    </li>
                </ul>

                <div v-else-if="activeState.loading" class="space-y-3">
                    <Skeleton v-for="n in 4" :key="n" class="h-16 w-full" />
                </div>

                <p
                    v-else-if="!activeState.error"
                    class="text-sm text-muted-foreground"
                >
                    No hotels found near {{ activeState.label }}.
                </p>

                <div
                    v-if="activeState.error"
                    class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
                >
                    <span>{{ activeState.error }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="loadPage(activeIndex)"
                    >
                        Try again
                    </Button>
                </div>

                <div
                    v-if="activeState.hasMore && !activeState.error"
                    class="mt-4 flex justify-center"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="activeState.loading"
                        @click="loadPage(activeIndex)"
                    >
                        <Spinner v-if="activeState.loading" class="size-4" />
                        Show more hotels
                    </Button>
                </div>
            </template>
        </CardContent>

        <TripHubHotelMapDialog v-model:open="mapOpen" :hotel="selectedHotel" />
    </Card>
</template>
