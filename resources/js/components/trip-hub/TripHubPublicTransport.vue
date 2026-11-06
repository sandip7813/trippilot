<script setup lang="ts">
import {
    Accessibility,
    Bike,
    Bus,
    CableCar,
    Car,
    CarTaxiFront,
    ChevronDown,
    ChevronUp,
    Clock,
    ExternalLink,
    Info,
    LayoutGrid,
    Mail,
    MapPin,
    Phone,
    Ship,
    TrainFront,
    TrainFrontTunnel,
    TramFront,
    Umbrella,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import TripHubMapDialog from '@/components/trip-hub/TripHubMapDialog.vue';
import type { MapPlace } from '@/components/trip-hub/TripHubMapDialog.vue';
import TripHubPlaceTabs from '@/components/trip-hub/TripHubPlaceTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useLazyPlaceData } from '@/composables/useLazyPlaceData';
import { formatMeters } from '@/lib/transport';
import { cn } from '@/lib/utils';
import type {
    PlacePublicTransport,
    TransitMode,
    TransitOperator,
    TransitStop,
    TransitStopGroup,
    TransportService,
    TransportServiceKind,
    TripPublicTransport,
} from '@/types/transport';

type IconAccent = {
    icon: Component;
    /** Gradient icon tile, as used by stat cards. */
    tile: string;
    /** Filter chip: resting and selected states. */
    chip: string;
    chipActive: string;
};

type ModeAccent = IconAccent & {
    /** Soft background for a mode card header. */
    tint: string;
};

type FilterChip = {
    /** "all", "mode:metro", "service:taxi", ... */
    id: string;
    label: string;
    count: number | null;
    icon: Component;
    chip: string;
    chipActive: string;
};

const props = defineProps<{
    tripId: string;
    publicTransport?: TripPublicTransport | null;
}>();

const modeStyles: Record<TransitMode, ModeAccent> = {
    metro: {
        icon: TrainFrontTunnel,
        tile: 'from-violet-500 to-purple-600 shadow-violet-500/25',
        tint: 'bg-violet-500/[0.06] dark:bg-violet-500/10',
        chip: 'bg-violet-500/10 text-violet-700 ring-violet-500/20 hover:bg-violet-500/15 dark:text-violet-300',
        chipActive:
            'bg-violet-600 text-white ring-violet-600 dark:bg-violet-500',
    },
    train: {
        icon: TrainFront,
        tile: 'from-sky-500 to-blue-600 shadow-sky-500/25',
        tint: 'bg-sky-500/[0.06] dark:bg-sky-500/10',
        chip: 'bg-sky-500/10 text-sky-700 ring-sky-500/20 hover:bg-sky-500/15 dark:text-sky-300',
        chipActive: 'bg-sky-600 text-white ring-sky-600 dark:bg-sky-500',
    },
    tram: {
        icon: TramFront,
        tile: 'from-amber-500 to-orange-500 shadow-amber-500/25',
        tint: 'bg-amber-500/[0.06] dark:bg-amber-500/10',
        chip: 'bg-amber-500/10 text-amber-700 ring-amber-500/20 hover:bg-amber-500/15 dark:text-amber-300',
        chipActive: 'bg-amber-600 text-white ring-amber-600 dark:bg-amber-500',
    },
    ferry: {
        icon: Ship,
        tile: 'from-cyan-500 to-teal-500 shadow-cyan-500/25',
        tint: 'bg-cyan-500/[0.06] dark:bg-cyan-500/10',
        chip: 'bg-cyan-500/10 text-cyan-700 ring-cyan-500/20 hover:bg-cyan-500/15 dark:text-cyan-300',
        chipActive: 'bg-cyan-600 text-white ring-cyan-600 dark:bg-cyan-500',
    },
    cable_car: {
        icon: CableCar,
        tile: 'from-rose-500 to-pink-500 shadow-rose-500/25',
        tint: 'bg-rose-500/[0.06] dark:bg-rose-500/10',
        chip: 'bg-rose-500/10 text-rose-700 ring-rose-500/20 hover:bg-rose-500/15 dark:text-rose-300',
        chipActive: 'bg-rose-600 text-white ring-rose-600 dark:bg-rose-500',
    },
    bus: {
        icon: Bus,
        tile: 'from-emerald-500 to-green-600 shadow-emerald-500/25',
        tint: 'bg-emerald-500/[0.06] dark:bg-emerald-500/10',
        chip: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 hover:bg-emerald-500/15 dark:text-emerald-300',
        chipActive:
            'bg-emerald-600 text-white ring-emerald-600 dark:bg-emerald-500',
    },
};

const serviceStyles: Record<
    TransportServiceKind,
    IconAccent & { label: string }
> = {
    taxi: {
        label: 'Taxi',
        icon: CarTaxiFront,
        tile: 'from-amber-400 to-orange-500 shadow-amber-500/25',
        chip: 'bg-orange-500/10 text-orange-700 ring-orange-500/20 hover:bg-orange-500/15 dark:text-orange-300',
        chipActive:
            'bg-orange-600 text-white ring-orange-600 dark:bg-orange-500',
    },
    car_rental: {
        label: 'Car rental',
        icon: Car,
        tile: 'from-indigo-500 to-blue-600 shadow-indigo-500/25',
        chip: 'bg-indigo-500/10 text-indigo-700 ring-indigo-500/20 hover:bg-indigo-500/15 dark:text-indigo-300',
        chipActive:
            'bg-indigo-600 text-white ring-indigo-600 dark:bg-indigo-500',
    },
    bike_rental: {
        label: 'Bike rental',
        icon: Bike,
        tile: 'from-lime-500 to-emerald-500 shadow-lime-500/25',
        chip: 'bg-lime-500/10 text-lime-700 ring-lime-500/20 hover:bg-lime-500/15 dark:text-lime-300',
        chipActive: 'bg-lime-600 text-white ring-lime-600 dark:bg-lime-500',
    },
};

const wheelchairLabels: Record<
    NonNullable<TransitStop['wheelchair']>,
    string
> = {
    yes: 'Step-free access',
    limited: 'Limited step-free access',
    no: 'No step-free access',
};

/** Stops shown per mode card before "View more". */
const collapsedStopCount = 5;
/** Bus route numbers shown per stop before "+N more". */
const collapsedRouteCount = 10;

const activeIndex = ref(0);
const activeFilter = ref('all');
const expandedGroups = ref(new Set<TransitMode>());
const expandedRouteStops = ref(new Set<string>());
const mapPlace = ref<MapPlace | null>(null);
const mapOpen = ref(false);

const places = useLazyPlaceData<PlacePublicTransport>(
    (key) =>
        TripController.publicTransport.url(
            { trip: props.tripId },
            { query: { location: Number(key) } },
        ),
    'Could not load public transport. Please try again.',
);

watch(
    () => props.publicTransport,
    (publicTransport) => {
        places.reset(
            Object.fromEntries(
                (publicTransport?.locations ?? []).map((location, index) => [
                    String(index),
                    location.stops !== null && location.services !== null
                        ? { stops: location.stops, services: location.services }
                        : null,
                ]),
            ),
        );
        activeIndex.value = 0;
    },
    { immediate: true },
);

watch(activeIndex, (index) => {
    activeFilter.value = 'all';
    expandedGroups.value = new Set();
    places.ensureLoaded(String(index));
});

const isLoading = computed(() => props.publicTransport === undefined);
const locations = computed(() => props.publicTransport?.locations ?? []);
const activeLocation = computed(
    () => locations.value[activeIndex.value] ?? null,
);
const activePlace = computed(
    () => places.states.value[String(activeIndex.value)] ?? null,
);
const showSkeleton = computed(
    () =>
        isLoading.value ||
        (activePlace.value !== null &&
            activePlace.value.data === null &&
            !activePlace.value.error),
);

const stopGroups = computed(() => activePlace.value?.data?.stops ?? []);
const services = computed(() => activePlace.value?.data?.services ?? []);

/** A real distance from this place, used as the example in the distance note. */
const exampleDistance = computed(
    () =>
        stopGroups.value[0]?.stops[0]?.distance_meters ??
        services.value[0]?.distance_meters ??
        null,
);

/**
 * "All", then one chip per transport mode and per kind of taxi or rental
 * service, each with how many places it lists.
 */
const filterChips = computed<FilterChip[]>(() => {
    const serviceCounts = new Map<TransportServiceKind, number>();

    for (const service of services.value) {
        serviceCounts.set(
            service.kind,
            (serviceCounts.get(service.kind) ?? 0) + 1,
        );
    }

    return [
        {
            id: 'all',
            label: 'All',
            count: null,
            icon: LayoutGrid,
            chip: 'bg-muted text-foreground ring-border hover:bg-muted/70',
            chipActive: 'bg-foreground text-background ring-foreground',
        },
        ...stopGroups.value.map((group) => ({
            id: `mode:${group.mode}`,
            label: group.label,
            count: group.stops.length,
            ...modeStyles[group.mode],
        })),
        ...(Object.keys(serviceStyles) as TransportServiceKind[])
            .filter((kind) => serviceCounts.has(kind))
            .map((kind) => ({
                id: `service:${kind}`,
                count: serviceCounts.get(kind) ?? 0,
                ...serviceStyles[kind],
            })),
    ];
});

const visibleGroups = computed(() => {
    if (activeFilter.value === 'all') {
        return stopGroups.value;
    }

    return stopGroups.value.filter(
        (group) => activeFilter.value === `mode:${group.mode}`,
    );
});

const visibleServices = computed(() => {
    if (activeFilter.value === 'all') {
        return services.value;
    }

    return services.value.filter(
        (service) => activeFilter.value === `service:${service.kind}`,
    );
});

const showStopsSection = computed(
    () =>
        activeFilter.value === 'all' || activeFilter.value.startsWith('mode:'),
);
const showServicesSection = computed(
    () =>
        activeFilter.value === 'all' ||
        activeFilter.value.startsWith('service:'),
);

function visibleStops(group: TransitStopGroup): TransitStop[] {
    return expandedGroups.value.has(group.mode)
        ? group.stops
        : group.stops.slice(0, collapsedStopCount);
}

function toggleGroup(group: TransitStopGroup): void {
    const expanded = new Set(expandedGroups.value);

    if (expanded.has(group.mode)) {
        expanded.delete(group.mode);
    } else {
        expanded.add(group.mode);
    }

    expandedGroups.value = expanded;
}

/**
 * "Delhi Metro · Delhi Metro Rail Corporation Limited", or whichever of the
 * two is known.
 */
function operatorLabel(operator: TransitOperator): string {
    return [...new Set([operator.network, operator.name])]
        .filter(Boolean)
        .join(' · ');
}

/**
 * A stop's own operator is only worth repeating when it differs from the
 * operators already shown for its group.
 */
function stopOperatorDiffers(
    group: TransitStopGroup,
    stop: TransitStop,
): boolean {
    if (!stop.operator && !stop.network) {
        return false;
    }

    return !group.operators.some(
        (operator) =>
            operator.name === stop.operator &&
            operator.network === stop.network,
    );
}

function routeKey(stop: TransitStop): string {
    return `${stop.lat},${stop.lng}`;
}

function visibleRoutes(stop: TransitStop): string[] {
    return expandedRouteStops.value.has(routeKey(stop))
        ? stop.routes
        : stop.routes.slice(0, collapsedRouteCount);
}

function expandRoutes(stop: TransitStop): void {
    expandedRouteStops.value = new Set(expandedRouteStops.value).add(
        routeKey(stop),
    );
}

/**
 * Distances are measured in a straight line from the place's own map point
 * (the city or area the traveler picked), not from a hotel.
 */
function distanceLabel(meters: number): string {
    return `${formatMeters(meters)} from the centre of ${activeLocation.value?.label ?? 'this place'}, in a straight line`;
}

/**
 * A single pin, not a route: the traveler's real starting point isn't known,
 * so a route from the place's centre would be misleading.
 */
function showPlaceOnMap(
    name: string,
    description: string,
    target: { lat: number; lng: number },
): void {
    mapPlace.value = {
        name,
        description,
        lat: target.lat,
        lng: target.lng,
    };
    mapOpen.value = true;
}

function showStopOnMap(group: TransitStopGroup, stop: TransitStop): void {
    showPlaceOnMap(
        stop.name,
        `${group.label} · ${distanceLabel(stop.distance_meters)}`,
        stop,
    );
}

function showServiceOnMap(service: TransportService): void {
    showPlaceOnMap(
        service.name,
        service.address ??
            `${service.kind_label} · ${distanceLabel(service.distance_meters)}`,
        service,
    );
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
            class="h-1.5 bg-gradient-to-r from-emerald-400 via-teal-500 to-cyan-500"
        />

        <CardHeader class="gap-3 pb-2">
            <div class="flex items-center gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-teal-500/25"
                >
                    <TrainFrontTunnel class="size-5" />
                </span>
                <div class="min-w-0">
                    <CardTitle class="text-lg font-bold">
                        Public transport
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Stations, stops, taxis and rentals
                        <template v-if="activeLocation">
                            near {{ activeLocation.label }}
                        </template>
                    </p>
                </div>
            </div>

            <TripHubPlaceTabs
                v-if="locations.length > 1"
                v-model:active="activeIndex"
                :labels="locations.map((location) => location.label)"
            />
        </CardHeader>

        <CardContent class="space-y-8">
            <template v-if="showSkeleton">
                <div class="flex flex-wrap gap-2">
                    <Skeleton
                        v-for="n in 5"
                        :key="n"
                        class="h-8 w-24 rounded-full"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Skeleton
                        v-for="n in 3"
                        :key="n"
                        class="h-56 w-full rounded-2xl"
                    />
                </div>
            </template>

            <div
                v-else-if="activePlace?.error"
                class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border/80 px-6 py-10 text-center text-sm text-muted-foreground"
            >
                <span>{{ activePlace.error }}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="places.load(String(activeIndex))"
                >
                    Try again
                </Button>
            </div>

            <template v-else-if="activeLocation && activePlace?.data">
                <!-- Filters: All, each mode, each kind of taxi or rental -->
                <div
                    v-if="filterChips.length > 1"
                    class="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Show transport by type"
                >
                    <button
                        v-for="chip in filterChips"
                        :key="chip.id"
                        type="button"
                        :aria-pressed="activeFilter === chip.id"
                        :class="
                            cn(
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition-colors',
                                activeFilter === chip.id
                                    ? cn('shadow-sm', chip.chipActive)
                                    : chip.chip,
                            )
                        "
                        @click="activeFilter = chip.id"
                    >
                        <component :is="chip.icon" class="size-3.5" />
                        {{ chip.label }}
                        <span
                            v-if="chip.count !== null"
                            :class="
                                cn(
                                    'min-w-5 rounded-full px-1.5 text-center text-[10px] leading-4',
                                    activeFilter === chip.id
                                        ? 'bg-white/25'
                                        : 'bg-background/70',
                                )
                            "
                        >
                            {{ chip.count }}
                        </span>
                    </button>
                </div>

                <!-- How the distances on this tab are measured -->
                <div
                    class="flex gap-3 rounded-xl border border-sky-500/25 bg-sky-500/[0.06] px-4 py-3 text-sm dark:bg-sky-500/10"
                >
                    <Info
                        class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400"
                    />
                    <div class="space-y-1">
                        <p class="font-medium">How distances are measured</p>
                        <p class="text-muted-foreground">
                            Distances<template v-if="exampleDistance !== null">
                                such as
                                <span
                                    class="inline-flex items-center gap-0.5 rounded-full bg-muted px-1.5 text-xs font-medium text-foreground"
                                    ><MapPin class="size-3" />{{
                                        formatMeters(exampleDistance)
                                    }}</span
                                ></template
                            >
                            are measured in a straight line from the centre of
                            <strong class="font-medium text-foreground">{{
                                activeLocation.label
                            }}</strong
                            >. That is the map point of the place in your trip,
                            not your hotel, so the walk is usually longer.
                            Select a distance to see the place on the map.
                        </p>
                    </div>
                </div>

                <!-- Stations and stops -->
                <section v-if="showStopsSection" class="space-y-4">
                    <h3 class="section-heading text-base">
                        Stations &amp; stops
                    </h3>

                    <div
                        v-if="visibleGroups.length > 0"
                        class="-mb-4 columns-1 gap-4 md:columns-2 xl:columns-3"
                    >
                        <article
                            v-for="group in visibleGroups"
                            :key="group.mode"
                            class="mb-4 break-inside-avoid overflow-hidden rounded-2xl border border-border/60 bg-background/60 shadow-sm"
                        >
                            <header
                                :class="
                                    cn(
                                        'flex items-center gap-3 border-b border-border/60 px-4 py-3',
                                        modeStyles[group.mode].tint,
                                    )
                                "
                            >
                                <span
                                    :class="
                                        cn(
                                            'flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md',
                                            modeStyles[group.mode].tile,
                                        )
                                    "
                                >
                                    <component
                                        :is="modeStyles[group.mode].icon"
                                        class="size-4"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">
                                        {{ group.label }}
                                    </p>
                                    <p
                                        v-for="operator in group.operators"
                                        :key="`${operator.network}|${operator.name}`"
                                        class="truncate text-xs text-muted-foreground"
                                        :title="operatorLabel(operator)"
                                    >
                                        {{ operatorLabel(operator) }}
                                    </p>
                                </div>
                            </header>

                            <ol class="divide-y divide-border/60">
                                <li
                                    v-for="stop in visibleStops(group)"
                                    :key="routeKey(stop)"
                                    class="space-y-2 px-4 py-3"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="flex min-w-0 flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="text-sm font-medium break-words"
                                            >
                                                {{ stop.name }}
                                            </span>
                                            <span
                                                v-if="stop.code"
                                                class="rounded-md border border-border/80 px-1.5 py-px font-mono text-[10px] font-semibold text-muted-foreground"
                                            >
                                                {{ stop.code }}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                            :title="`${distanceLabel(stop.distance_meters)}. Show on map.`"
                                            :aria-label="`Show ${stop.name} on a map, ${distanceLabel(stop.distance_meters)}`"
                                            @click="showStopOnMap(group, stop)"
                                        >
                                            <MapPin class="size-3" />
                                            {{
                                                formatMeters(
                                                    stop.distance_meters,
                                                )
                                            }}
                                        </button>
                                    </div>

                                    <p
                                        v-if="stopOperatorDiffers(group, stop)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            operatorLabel({
                                                name: stop.operator,
                                                network: stop.network,
                                            })
                                        }}
                                    </p>

                                    <div
                                        v-if="stop.routes.length > 0"
                                        class="flex flex-wrap items-center gap-1"
                                    >
                                        <span
                                            v-for="route in visibleRoutes(stop)"
                                            :key="route"
                                            class="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] font-medium text-foreground/80"
                                        >
                                            {{ route }}
                                        </span>
                                        <button
                                            v-if="
                                                stop.routes.length >
                                                visibleRoutes(stop).length
                                            "
                                            type="button"
                                            class="rounded-md px-1.5 py-0.5 text-[11px] font-medium text-primary hover:bg-primary/10"
                                            @click="expandRoutes(stop)"
                                        >
                                            +{{
                                                stop.routes.length -
                                                visibleRoutes(stop).length
                                            }}
                                            more
                                        </button>
                                    </div>

                                    <div
                                        v-if="stop.wheelchair || stop.shelter"
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="stop.wheelchair"
                                            :class="
                                                cn(
                                                    'inline-flex items-center gap-1',
                                                    stop.wheelchair === 'yes' &&
                                                        'text-emerald-600 dark:text-emerald-400',
                                                )
                                            "
                                        >
                                            <Accessibility class="size-3" />
                                            {{
                                                wheelchairLabels[
                                                    stop.wheelchair
                                                ]
                                            }}
                                        </span>
                                        <span
                                            v-if="stop.shelter"
                                            class="inline-flex items-center gap-1"
                                        >
                                            <Umbrella class="size-3" />
                                            Covered stop
                                        </span>
                                    </div>
                                </li>
                            </ol>

                            <button
                                v-if="group.stops.length > collapsedStopCount"
                                type="button"
                                class="flex w-full items-center justify-center gap-1 border-t border-border/60 px-4 py-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/5"
                                :aria-expanded="expandedGroups.has(group.mode)"
                                @click="toggleGroup(group)"
                            >
                                <template v-if="expandedGroups.has(group.mode)">
                                    Show less
                                    <ChevronUp class="size-3.5" />
                                </template>
                                <template v-else>
                                    View
                                    {{
                                        group.stops.length - collapsedStopCount
                                    }}
                                    more
                                    <ChevronDown class="size-3.5" />
                                </template>
                            </button>
                        </article>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border/80 px-6 py-10 text-center text-sm text-muted-foreground"
                    >
                        <TrainFrontTunnel class="size-6 opacity-60" />
                        No public transport stops found near
                        {{ activeLocation.label }}.
                    </div>
                </section>

                <!-- Taxis and rentals -->
                <section v-if="showServicesSection" class="space-y-4">
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                    >
                        <h3 class="section-heading text-base">
                            Taxis &amp; rentals
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Taxi stands and car or bike rentals you can contact
                        </p>
                    </div>

                    <div
                        v-if="visibleServices.length > 0"
                        class="-mb-4 columns-1 gap-4 sm:columns-2 xl:columns-3"
                    >
                        <article
                            v-for="service in visibleServices"
                            :key="`${service.lat},${service.lng},${service.name}`"
                            class="mb-4 flex break-inside-avoid flex-col gap-3 rounded-2xl border border-border/60 bg-background/60 p-4 shadow-sm"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    :class="
                                        cn(
                                            'flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md',
                                            serviceStyles[service.kind].tile,
                                        )
                                    "
                                >
                                    <component
                                        :is="serviceStyles[service.kind].icon"
                                        class="size-4"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="leading-snug font-semibold break-words"
                                    >
                                        {{ service.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ service.kind_label }}
                                        <template
                                            v-if="
                                                service.operator &&
                                                service.operator !==
                                                    service.name
                                            "
                                        >
                                            · {{ service.operator }}
                                        </template>
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                    :title="`${distanceLabel(service.distance_meters)}. Show on map.`"
                                    :aria-label="`Show ${service.name} on a map, ${distanceLabel(service.distance_meters)}`"
                                    @click="showServiceOnMap(service)"
                                >
                                    <MapPin class="size-3" />
                                    {{ formatMeters(service.distance_meters) }}
                                </button>
                            </div>

                            <div
                                v-if="service.address || service.opening_hours"
                                class="space-y-1.5 text-xs text-muted-foreground"
                            >
                                <p
                                    v-if="service.address"
                                    class="flex items-start gap-1.5 break-words"
                                >
                                    <MapPin class="mt-px size-3 shrink-0" />
                                    {{ service.address }}
                                </p>
                                <p
                                    v-if="service.opening_hours"
                                    class="flex items-start gap-1.5"
                                >
                                    <Clock class="mt-px size-3 shrink-0" />
                                    {{ service.opening_hours }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    service.phones.length > 0 ||
                                    service.website ||
                                    service.email
                                "
                                class="flex flex-wrap gap-2 border-t border-border/60 pt-3"
                            >
                                <Button
                                    v-for="phone in service.phones"
                                    :key="phone"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="h-8"
                                >
                                    <a
                                        :href="`tel:${phone.replace(/[^\d+]/g, '')}`"
                                    >
                                        <Phone class="size-3.5" />
                                        {{ phone }}
                                    </a>
                                </Button>
                                <Button
                                    v-if="service.website"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="h-8 max-w-full"
                                >
                                    <a
                                        :href="service.website"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink class="size-3.5" />
                                        <span class="truncate">
                                            {{ websiteHost(service.website) }}
                                        </span>
                                    </a>
                                </Button>
                                <Button
                                    v-if="service.email"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="h-8"
                                >
                                    <a
                                        :href="`mailto:${service.email}`"
                                        :title="service.email"
                                    >
                                        <Mail class="size-3.5" />
                                        Email
                                    </a>
                                </Button>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border/80 px-6 py-10 text-center text-sm text-muted-foreground"
                    >
                        <CarTaxiFront class="size-6 opacity-60" />
                        No taxi stands or rental shops with contact details are
                        listed near {{ activeLocation.label }}.
                    </div>
                </section>
            </template>

            <p
                v-if="!showSkeleton"
                class="border-t border-border/60 pt-4 text-xs text-muted-foreground"
            >
                Stops, operators, routes, taxis and rentals from Geoapify and
                OpenStreetMap, which are community-maintained, so check details
                before you travel.
            </p>
        </CardContent>

        <TripHubMapDialog v-model:open="mapOpen" :place="mapPlace" />
    </Card>
</template>
