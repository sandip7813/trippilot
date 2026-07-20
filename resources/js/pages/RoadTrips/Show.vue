<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Bike,
    Car,
    Check,
    Clock,
    Copy,
    EyeOff,
    Map as MapIcon,
    MapPin,
    Maximize2,
    Minimize2,
    Navigation,
    Pencil,
    RefreshCw,
    Route,
    Sparkles,
    Trash2,
    Zap,
} from '@lucide/vue';
import {
    computed,
    defineAsyncComponent,
    onMounted,
    onUnmounted,
    ref,
} from 'vue';
import RoadTripController from '@/actions/App/Http/Controllers/RoadTripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import TripCoverPlaceholder from '@/components/TripCoverPlaceholder.vue';
import TripCoverRegenerateButton from '@/components/TripCoverRegenerateButton.vue';
import TripCoverUploadButton from '@/components/TripCoverUploadButton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTripCoverAutoRefresh } from '@/composables/useTripCoverAutoRefresh';
import { useTripRouteStops } from '@/composables/useTripRouteStops';
import { cn } from '@/lib/utils';
import { edit, index as roadTripsIndex } from '@/routes/road-trips';
import {
    amenityLayerLabels,
    amenityLayerStyle,
    amenityPlaceKey,
    amenityRouteZoneLabel,
    breakDisplayReason,
    breakKindLabel,
    formatDrivingDistance,
    formatDrivingDuration,
    hasCalculatedRoute,
    stopDisplayAddress,
} from '@/types/roadTrip';
import type {
    RoadTrip,
    RoadTripFormOptions,
    RoadTripPlace,
} from '@/types/roadTrip';
import type { TripOption } from '@/types/trip';
import { locationLabel } from '@/types/trip';
import TripWeatherCard from '@/components/TripWeatherCard.vue';
import TripChatPanel from '@/components/trip-hub/TripChatPanel.vue';
import type { TripWeather } from '@/types/weather';

const RoadTripMap = defineAsyncComponent({
    loader: () => import('@/components/road-trips/RoadTripMap.vue'),
    ssr: false,
});

const props = defineProps<
    RoadTripFormOptions & {
        trip: RoadTrip;
        mapsConfigured: boolean;
        aiConfigured: boolean;
        amenityLayers: string[];
        weather: TripWeather | null;
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Road Trips', href: roadTripsIndex() }],
    },
});

type PanelTab = 'tools' | 'amenities' | 'breaks' | 'stops';

const page = usePage();
const activeAmenityLayer = ref<string | null>(null);
const focusedAmenityPlaceKey = ref<string | null>(null);
const copiedAmenityPlaceKey = ref<string | null>(null);
const activePanel = ref<PanelTab>('amenities');

const isBicycleTrip = computed(
    () => props.trip.road_profile?.vehicle_class === 'bicycle',
);

const errors = computed(
    () => (page.props.errors as Record<string, string> | undefined) ?? {},
);

const hasRoute = computed(() => hasCalculatedRoute(props.trip.route));

const { routeChainLabels, routeMapPoints, hasMultiStopRoute } =
    useTripRouteStops({
        trip: props.trip,
        routeSummary: props.trip.route_summary,
    });

const { waitingForCover } = useTripCoverAutoRefresh();

const bannerExpanded = ref(false);

const hasRoutePolyline = computed(
    () => (props.trip.route?.polyline?.length ?? 0) >= 2,
);

const activeAmenityPlaces = computed(() => {
    if (!activeAmenityLayer.value || !props.trip.amenities_cache) {
        return [];
    }

    return props.trip.amenities_cache[activeAmenityLayer.value]?.places ?? [];
});

const vehicleLabel = computed(() =>
    optionLabel(props.vehicleClasses, props.trip.road_profile?.vehicle_class),
);

const fuelLabel = computed(() =>
    optionLabel(props.fuelTypes, props.trip.road_profile?.fuel_type),
);

const panelTabs = computed(() => {
    const tabs: Array<{
        id: PanelTab;
        label: string;
        count?: number;
    }> = [{ id: 'tools', label: 'Tools' }];

    if (hasRoute.value) {
        tabs.push({
            id: 'amenities',
            label: 'Amenities',
            count: props.amenityLayers.reduce(
                (total, layer) => total + amenityCount(layer),
                0,
            ),
        });
    }

    if (props.trip.suggested_breaks.length > 0) {
        tabs.push({
            id: 'breaks',
            label: 'Breaks',
            count: props.trip.suggested_breaks.length,
        });
    }

    if (props.trip.stops.length > 0) {
        tabs.push({
            id: 'stops',
            label: 'Stops',
            count: props.trip.stops.length,
        });
    }

    return tabs;
});

function optionLabel(options: TripOption[], value: string | undefined): string {
    return (
        options.find((option) => option.value === value)?.label ?? value ?? '—'
    );
}

function amenityCount(layer: string): number {
    return props.trip.amenities_cache?.[layer]?.places.length ?? 0;
}

function selectAmenityLayer(layer: string): void {
    activeAmenityLayer.value = layer;
    focusedAmenityPlaceKey.value = null;
}

async function copyAmenityPlace(place: RoadTripPlace): Promise<void> {
    const text = place.address?.trim() || place.name;

    try {
        await navigator.clipboard.writeText(text);
        copiedAmenityPlaceKey.value = amenityPlaceKey(place);
        window.setTimeout(() => {
            if (copiedAmenityPlaceKey.value === amenityPlaceKey(place)) {
                copiedAmenityPlaceKey.value = null;
            }
        }, 2000);
    } catch {
        copiedAmenityPlaceKey.value = null;
    }
}

function focusAmenityOnMap(place: RoadTripPlace): void {
    if (!activeAmenityLayer.value) {
        return;
    }

    focusedAmenityPlaceKey.value = amenityPlaceKey(place);
}

function clearAmenityLayer(): void {
    activeAmenityLayer.value = null;
    focusedAmenityPlaceKey.value = null;
}

function selectPanel(tab: PanelTab): void {
    activePanel.value = tab;
}

function handleFlash(event: Event): void {
    const flash = (event as CustomEvent).detail?.flash as
        { amenityLayer?: string } | undefined;
    const layer = flash?.amenityLayer;

    if (typeof layer === 'string' && layer !== '') {
        activeAmenityLayer.value = layer;
        focusedAmenityPlaceKey.value = null;
        activePanel.value = 'amenities';
    }
}

let removeFlashListener: (() => void) | undefined;

onMounted(() => {
    removeFlashListener = router.on('flash', handleFlash);

    if (!hasRoute.value) {
        activePanel.value = 'tools';
    }
});

onUnmounted(() => {
    removeFlashListener?.();
});
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <TripCoverPlaceholder
            v-if="!trip.cover_image_url"
            :exhausted="Boolean(trip.cover_image_exhausted)"
            :pending="waitingForCover"
            :sync-cover-form="RoadTripController.syncCover.form(trip.id)"
            :upload-cover-form="RoadTripController.uploadCover.form(trip.id)"
            class="-mx-4 md:-mx-6"
        />

        <div
            v-else
            class="relative -mx-4 overflow-hidden rounded-2xl border border-border/70 shadow-md md:-mx-6"
            :class="bannerExpanded ? 'bg-muted/30' : ''"
        >
            <div
                :class="
                    cn(
                        'w-full overflow-hidden',
                        bannerExpanded
                            ? 'aspect-[21/9] min-h-[13rem] sm:min-h-[16rem] lg:min-h-[20rem]'
                            : 'h-36 md:h-44',
                    )
                "
            >
                <img
                    :key="`${trip.cover_image_version}-${trip.cover_image_url}`"
                    :src="trip.cover_image_url"
                    :alt="`${trip.title} cover`"
                    width="1920"
                    height="900"
                    fetchpriority="high"
                    decoding="async"
                    :class="
                        cn(
                            'size-full',
                            bannerExpanded
                                ? 'object-contain object-center'
                                : 'object-cover',
                        )
                    "
                />
            </div>
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-t from-background/80 via-transparent to-transparent"
            />
            <div class="absolute top-3 right-3 z-10 flex items-center gap-2">
                <TripCoverRegenerateButton
                    :form-binding="RoadTripController.syncCover.form(trip.id)"
                    :has-cover="true"
                    :exhausted="Boolean(trip.cover_image_exhausted)"
                    variant="secondary"
                    size="icon"
                    class="size-8 border-border/60 bg-background/90 shadow-sm backdrop-blur-sm"
                />
                <TripCoverUploadButton
                    :form-binding="RoadTripController.uploadCover.form(trip.id)"
                    variant="secondary"
                    size="icon"
                    class="size-8 border-border/60 bg-background/90 shadow-sm backdrop-blur-sm"
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    class="size-8 border-border/60 bg-background/90 shadow-sm backdrop-blur-sm"
                    :title="
                        bannerExpanded ? 'Collapse banner' : 'Expand banner'
                    "
                    @click="bannerExpanded = !bannerExpanded"
                >
                    <Minimize2 v-if="bannerExpanded" class="size-4" />
                    <Maximize2 v-else class="size-4" />
                </Button>
            </div>
        </div>

        <p
            v-if="trip.cover_image_url && trip.cover_image_source_label"
            class="-mt-2 text-xs text-muted-foreground"
        >
            Photo: {{ trip.cover_image_source_label }}
        </p>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Button variant="ghost" size="sm" class="-ml-2 h-8" as-child>
                    <Link :href="roadTripsIndex()">
                        <ArrowLeft class="mr-1 size-4" />
                        Road trips
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="edit(trip.id)">
                        <Pencil class="mr-1.5 size-4" />
                        Edit trip
                    </Link>
                </Button>
            </div>

            <div
                class="grid grid-cols-1 items-start gap-x-8 gap-y-3 md:grid-cols-[minmax(0,1fr)_auto]"
            >
                <div class="min-w-0 space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                        <span class="brand-gradient-text">{{
                            trip.title
                        }}</span>
                    </h1>

                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                    >
                        <template v-if="hasMultiStopRoute">
                            <template
                                v-for="(label, index) in routeChainLabels"
                                :key="`${label}-${index}`"
                            >
                                <span
                                    v-if="index > 0"
                                    class="inline-flex items-center gap-1 text-muted-foreground"
                                >
                                    <span class="h-px w-4 bg-border sm:w-6" />
                                    <Route
                                        class="size-3.5 shrink-0 text-teal-600"
                                    />
                                    <span class="h-px w-4 bg-border sm:w-6" />
                                </span>
                                <span class="font-medium text-foreground">
                                    {{ label }}
                                </span>
                            </template>
                        </template>
                        <template v-else>
                            <span class="font-medium text-foreground">
                                {{ locationLabel(trip.origin) || 'Origin' }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 text-muted-foreground"
                            >
                                <span class="h-px w-6 bg-border sm:w-10" />
                                <Route
                                    class="size-3.5 shrink-0 text-teal-600"
                                />
                                <span class="h-px w-6 bg-border sm:w-10" />
                            </span>
                            <span class="font-medium text-foreground">
                                {{
                                    locationLabel(trip.destination) ||
                                    'Destination'
                                }}
                            </span>
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <Badge variant="secondary" class="gap-1 font-normal">
                            <Bike v-if="isBicycleTrip" class="size-3" />
                            <Car v-else class="size-3" />
                            {{ vehicleLabel }}
                        </Badge>
                        <Badge
                            v-if="
                                !isBicycleTrip && trip.road_profile?.fuel_type
                            "
                            variant="outline"
                            class="gap-1 font-normal"
                        >
                            <Zap class="size-3" />
                            {{ fuelLabel }}
                        </Badge>
                        <Badge
                            v-if="hasRoute && trip.route!.has_tolls"
                            variant="outline"
                            class="font-normal"
                        >
                            Tolls on route
                        </Badge>
                    </div>
                </div>

                <div
                    v-if="hasRoute"
                    class="flex shrink-0 flex-col gap-3 md:items-end"
                >
                    <div
                        class="flex flex-wrap items-center gap-4 text-sm md:justify-end"
                    >
                        <div class="text-left md:text-right">
                            <p
                                class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Distance
                            </p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 font-semibold md:justify-end"
                            >
                                <Navigation class="size-3.5 text-teal-600" />
                                {{
                                    formatDrivingDistance(
                                        trip.route!.distance_km,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="text-left md:text-right">
                            <p
                                class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Drive time
                            </p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 font-semibold md:justify-end"
                            >
                                <Clock class="size-3.5 text-sky-600" />
                                {{
                                    formatDrivingDuration(
                                        trip.route!.duration_seconds,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 md:justify-end"
                    >
                        <Form
                            v-bind="
                                RoadTripController.computeRoute.form(trip.id)
                            "
                            v-slot="{ processing }"
                            class="relative"
                        >
                            <FormSavingOverlay
                                :show="processing"
                                message="Recalculating..."
                            />
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                :disabled="processing || !mapsConfigured"
                            >
                                <Spinner v-if="processing" class="mr-1.5" />
                                <RefreshCw v-else class="mr-1.5 size-4" />
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
                                message="Finding breaks..."
                            />
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    processing || !hasRoute || !aiConfigured
                                "
                            >
                                <Spinner v-if="processing" class="mr-1.5" />
                                <Sparkles v-else class="mr-1.5 size-4" />
                                Suggest breaks with AI
                            </Button>
                        </Form>
                    </div>
                </div>
            </div>

            <InputError :message="errors.route" />
            <InputError :message="errors.ai" />
            <InputError :message="errors.stop" />
        </div>

        <Alert v-if="!mapsConfigured">
            <MapPin class="size-4" />
            <AlertTitle>Maps not configured</AlertTitle>
            <AlertDescription>
                Add GEOAPIFY_API_KEY to calculate routes and load amenities
                along your drive.
            </AlertDescription>
        </Alert>

        <div
            class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-xl shadow-teal-500/5"
        >
            <div
                class="relative min-h-[420px] sm:min-h-[480px] lg:min-h-[540px]"
            >
                <RoadTripMap
                    :origin="trip.origin"
                    :destination="trip.destination"
                    :route="trip.route"
                    :city-points="hasMultiStopRoute ? routeMapPoints : []"
                    :stops="trip.stops"
                    :suggested-breaks="trip.suggested_breaks"
                    :amenity-places="activeAmenityPlaces"
                    :active-amenity-layer="activeAmenityLayer"
                    :focused-amenity-place-key="focusedAmenityPlaceKey"
                />

                <div
                    v-if="activeAmenityLayer"
                    class="absolute top-3 left-3 z-[1000] flex max-w-[min(100%-1.5rem,20rem)] items-center gap-2 rounded-xl border border-white/20 bg-background/90 px-3 py-2 text-sm shadow-lg backdrop-blur-md"
                >
                    <span
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-xs text-white"
                        :style="{
                            backgroundColor:
                                amenityLayerStyle(activeAmenityLayer).color,
                        }"
                    >
                        {{ amenityLayerStyle(activeAmenityLayer).glyph }}
                    </span>
                    <span class="min-w-0 flex-1 truncate font-medium">
                        {{
                            amenityLayerLabels[activeAmenityLayer] ??
                            activeAmenityLayer
                        }}
                        ({{ activeAmenityPlaces.length }})
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        title="Hide layer"
                        @click="clearAmenityLayer()"
                    >
                        <EyeOff class="size-4" />
                    </Button>
                </div>
            </div>

            <p
                v-if="!hasRoutePolyline && hasRoute"
                class="border-t border-border/60 px-4 py-2.5 text-xs text-muted-foreground"
            >
                Distance and drive time are saved, but the map line is missing.
                Click <strong>Recalculate</strong> to redraw it.
            </p>
        </div>

        <TripWeatherCard :weather="weather" class="h-auto" />

        <div
            class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm"
        >
            <div
                class="flex gap-1 overflow-x-auto border-b border-border/60 bg-muted/20 p-1.5"
                role="tablist"
            >
                <button
                    v-for="tab in panelTabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :aria-selected="activePanel === tab.id"
                    :class="
                        cn(
                            'inline-flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                            activePanel === tab.id
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )
                    "
                    @click="selectPanel(tab.id)"
                >
                    {{ tab.label }}
                    <Badge
                        v-if="tab.count !== undefined && tab.count > 0"
                        variant="secondary"
                        class="h-5 min-w-5 justify-center px-1.5 text-[10px]"
                    >
                        {{ tab.count }}
                    </Badge>
                </button>
            </div>

            <div class="p-4 md:p-5">
                <div v-show="activePanel === 'tools'" class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold">Route tools</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Recalculate your route after changing stops, or let
                            AI suggest fuel, food, and rest breaks along the
                            way.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
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
                            <button
                                type="submit"
                                class="flex w-full items-start gap-3 rounded-xl border border-border/60 bg-muted/10 p-4 text-left transition-colors hover:border-teal-500/40 hover:bg-teal-500/5 disabled:opacity-50"
                                :disabled="processing || !mapsConfigured"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-teal-500/15 text-teal-600"
                                >
                                    <Spinner v-if="processing" class="size-5" />
                                    <RefreshCw v-else class="size-5" />
                                </span>
                                <span>
                                    <span class="block font-medium">
                                        Recalculate route
                                    </span>
                                    <span
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                    >
                                        Refresh distance, time, and map line
                                    </span>
                                </span>
                            </button>
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
                                message="Finding break suggestions..."
                            />
                            <button
                                type="submit"
                                class="flex w-full items-start gap-3 rounded-xl border border-border/60 bg-muted/10 p-4 text-left transition-colors hover:border-violet-500/40 hover:bg-violet-500/5 disabled:opacity-50"
                                :disabled="
                                    processing || !hasRoute || !aiConfigured
                                "
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-500/15 text-violet-600"
                                >
                                    <Spinner v-if="processing" class="size-5" />
                                    <Sparkles v-else class="size-5" />
                                </span>
                                <span>
                                    <span class="block font-medium">
                                        Suggest breaks with AI
                                    </span>
                                    <span
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                    >
                                        Fuel, meals, and rest stops on your
                                        route
                                    </span>
                                </span>
                            </button>
                        </Form>
                    </div>
                </div>

                <div v-show="activePanel === 'amenities'" class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold">
                            Amenities along route
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Choose a category on the left, then browse places on
                            the right. Use the map icon to highlight a place on
                            the map above.
                            <span v-if="isBicycleTrip">
                                Bicycle trips hide fuel, EV, and parking.
                            </span>
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(10rem,13rem)_minmax(0,1fr)]"
                    >
                        <nav
                            class="flex flex-col gap-1 rounded-xl border border-border/60 bg-muted/10 p-1.5"
                            aria-label="Amenity categories"
                        >
                            <div
                                v-for="layer in amenityLayers"
                                :key="layer"
                                :class="
                                    cn(
                                        'flex items-center gap-1 rounded-lg transition-colors',
                                        activeAmenityLayer === layer
                                            ? 'bg-background shadow-sm ring-1 ring-teal-500/30'
                                            : 'hover:bg-background/70',
                                    )
                                "
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-2 px-2 py-2 text-left"
                                    @click="selectAmenityLayer(layer)"
                                >
                                    <span
                                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-sm text-white shadow-sm"
                                        :style="{
                                            backgroundColor:
                                                amenityLayerStyle(layer).color,
                                        }"
                                    >
                                        {{ amenityLayerStyle(layer).glyph }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-xs leading-tight font-medium"
                                        >
                                            {{
                                                amenityLayerLabels[layer] ??
                                                layer
                                            }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                amenityCount(layer) > 0
                                                    ? `${amenityCount(layer)} places`
                                                    : 'Not loaded'
                                            }}
                                        </span>
                                    </span>
                                </button>

                                <Form
                                    v-bind="
                                        RoadTripController.amenities.form(
                                            trip.id,
                                            { query: { layer } },
                                        )
                                    "
                                    v-slot="{ processing }"
                                    class="shrink-0 pr-1"
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        :title="
                                            amenityCount(layer) > 0
                                                ? 'Refresh'
                                                : 'Load'
                                        "
                                        :disabled="
                                            processing || !mapsConfigured
                                        "
                                    >
                                        <Spinner
                                            v-if="processing"
                                            class="size-3.5"
                                        />
                                        <RefreshCw v-else class="size-3.5" />
                                    </Button>
                                </Form>
                            </div>
                        </nav>

                        <div
                            class="min-w-0 overflow-hidden rounded-xl border border-border/60 bg-muted/5"
                        >
                            <div
                                class="border-b border-border/60 bg-muted/20 px-4 py-3"
                            >
                                <p
                                    v-if="activeAmenityLayer"
                                    class="text-sm font-medium"
                                >
                                    {{
                                        amenityLayerLabels[
                                            activeAmenityLayer
                                        ] ?? activeAmenityLayer
                                    }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                    >
                                        ({{ activeAmenityPlaces.length }})
                                    </span>
                                </p>
                                <p v-else class="text-sm text-muted-foreground">
                                    Select an amenity category
                                </p>
                            </div>

                            <div
                                v-if="!activeAmenityLayer"
                                class="px-4 py-10 text-center text-sm text-muted-foreground"
                            >
                                Pick a category from the left to see places
                                along your route.
                            </div>

                            <div
                                v-else-if="activeAmenityPlaces.length === 0"
                                class="px-4 py-10 text-center text-sm text-muted-foreground"
                            >
                                No places loaded yet. Click the refresh button
                                next to
                                {{
                                    amenityLayerLabels[activeAmenityLayer] ??
                                    activeAmenityLayer
                                }}
                                to fetch results.
                            </div>

                            <ul
                                v-else
                                class="max-h-[calc(9*4.75rem)] divide-y divide-border/60 overflow-y-auto"
                            >
                                <li
                                    v-for="place in activeAmenityPlaces"
                                    :key="amenityPlaceKey(place)"
                                    :class="
                                        cn(
                                            'flex items-start gap-3 px-4 py-3 transition-colors',
                                            focusedAmenityPlaceKey ===
                                                amenityPlaceKey(place) &&
                                                'bg-teal-500/5',
                                        )
                                    "
                                >
                                    <span
                                        class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full text-xs text-white"
                                        :style="{
                                            backgroundColor:
                                                amenityLayerStyle(
                                                    activeAmenityLayer,
                                                ).color,
                                        }"
                                    >
                                        {{
                                            amenityLayerStyle(
                                                activeAmenityLayer,
                                            ).glyph
                                        }}
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">
                                            {{ place.name }}
                                        </p>
                                        <p
                                            v-if="place.address"
                                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                        >
                                            {{ place.address }}
                                        </p>
                                        <Badge
                                            v-if="
                                                amenityRouteZoneLabel(
                                                    place.route_zone,
                                                )
                                            "
                                            variant="outline"
                                            class="mt-2 h-5 px-1.5 text-[10px] font-normal"
                                        >
                                            {{
                                                amenityRouteZoneLabel(
                                                    place.route_zone,
                                                )
                                            }}
                                        </Badge>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            title="Copy address"
                                            @click="copyAmenityPlace(place)"
                                        >
                                            <Check
                                                v-if="
                                                    copiedAmenityPlaceKey ===
                                                    amenityPlaceKey(place)
                                                "
                                                class="size-4 text-teal-600"
                                            />
                                            <Copy v-else class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            title="Show on map"
                                            @click="focusAmenityOnMap(place)"
                                        >
                                            <MapIcon class="size-4" />
                                        </Button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div v-show="activePanel === 'breaks'" class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold">
                            Suggested breaks
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            AI-recommended stops along your route. Add any to
                            your trip.
                        </p>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-2">
                        <article
                            v-for="breakItem in trip.suggested_breaks"
                            :key="breakItem.id"
                            class="flex flex-col rounded-xl border border-border/60 bg-muted/10 p-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">
                                    {{ breakItem.title }}
                                </span>
                                <Badge variant="secondary">
                                    {{ breakKindLabel(breakItem.kind) }}
                                </Badge>
                            </div>
                            <p
                                v-if="breakDisplayReason(breakItem.reason)"
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ breakDisplayReason(breakItem.reason) }}
                            </p>
                            <p
                                v-if="breakItem.address"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{ breakItem.address }}
                            </p>

                            <Form
                                v-bind="
                                    RoadTripController.acceptBreak.form(trip.id)
                                "
                                v-slot="{ processing }"
                                class="mt-4"
                            >
                                <input
                                    type="hidden"
                                    name="break_id"
                                    :value="breakItem.id"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    :disabled="processing"
                                >
                                    <Spinner v-if="processing" class="mr-1.5" />
                                    Add as stop
                                    <ArrowRight class="ml-1.5 size-4" />
                                </Button>
                            </Form>
                        </article>
                    </div>
                </div>

                <div v-show="activePanel === 'stops'" class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold">Your stops</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Planned stops on this road trip, in order.
                        </p>
                    </div>

                    <ol
                        class="space-y-0 divide-y divide-border/60 rounded-xl border border-border/60"
                    >
                        <li
                            v-for="(stop, index) in trip.stops"
                            :key="`${stop.label}-${index}`"
                            class="flex items-start gap-3 bg-muted/5 p-4"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-teal-500/15 text-sm font-bold text-teal-700 dark:text-teal-300"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ stop.label }}</p>
                                <p
                                    v-if="stopDisplayAddress(stop)"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ stopDisplayAddress(stop) }}
                                </p>
                                <p
                                    v-if="breakDisplayReason(stop.notes)"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ breakDisplayReason(stop.notes) }}
                                </p>
                            </div>
                            <Form
                                v-bind="
                                    RoadTripController.removeStop.form(trip.id)
                                "
                                v-slot="{ processing }"
                                class="relative shrink-0"
                            >
                                <FormSavingOverlay
                                    :show="processing"
                                    message="Removing stop..."
                                />
                                <input
                                    type="hidden"
                                    name="stop_index"
                                    :value="index"
                                />
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    title="Remove stop"
                                    :disabled="processing"
                                >
                                    <Spinner v-if="processing" class="size-4" />
                                    <Trash2 v-else class="size-4" />
                                </Button>
                            </Form>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <TripChatPanel
            v-if="aiConfigured"
            :trip="trip"
            :ai-configured="aiConfigured"
            variant="road"
        />
    </div>
</template>
