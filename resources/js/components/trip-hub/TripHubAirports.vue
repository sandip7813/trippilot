<script setup lang="ts">
import { Bus, Car, Plane, PlaneTakeoff, Route } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import TripHubMapDialog from '@/components/trip-hub/TripHubMapDialog.vue';
import type { MapPlace } from '@/components/trip-hub/TripHubMapDialog.vue';
import TripHubPlaceTabs from '@/components/trip-hub/TripHubPlaceTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useLazyPlaceData } from '@/composables/useLazyPlaceData';
import { formatKm, formatMinutes } from '@/lib/transport';
import { cn } from '@/lib/utils';
import type {
    AirportTransferResult,
    TripAirport,
    TripAirports,
} from '@/types/transport';

const props = defineProps<{
    tripId: string;
    airports?: TripAirports | null;
}>();

const activeIndex = ref(0);

/** The airport picked for each place, by place index. Defaults to the nearest. */
const selectedAirportCodes = ref<Record<number, string>>({});

/** Transfers are loaded per place and airport, e.g. "0|DEL". */
function transferKey(locationIndex: number, airportCode: string): string {
    return `${locationIndex}|${airportCode}`;
}

const transfers = useLazyPlaceData<AirportTransferResult>((key) => {
    const [location, airport] = key.split('|');

    return TripController.airportTransfer.url(
        { trip: props.tripId },
        { query: { location: Number(location), airport } },
    );
}, 'Could not load the airport transfer. Please try again.');

watch(
    () => props.airports,
    (airports) => {
        // The server sends the transfer from the nearest airport of the
        // first place; everything else loads when it is selected.
        transfers.reset(
            Object.fromEntries(
                (airports?.locations ?? []).flatMap((location, index) =>
                    location.transfer_loaded && location.airports[0]
                        ? [
                              [
                                  transferKey(
                                      index,
                                      location.airports[0].iata_code,
                                  ),
                                  { transfer: location.transfer },
                              ],
                          ]
                        : [],
                ),
            ),
        );
        selectedAirportCodes.value = {};
        activeIndex.value = 0;
    },
    { immediate: true },
);

const isLoading = computed(() => props.airports === undefined);
const transfersEnabled = computed(
    () => props.airports?.transfers_enabled ?? false,
);
const locations = computed(() => props.airports?.locations ?? []);
const departure = computed(() => props.airports?.departure ?? null);
const activeLocation = computed(
    () => locations.value[activeIndex.value] ?? null,
);
const selectedAirport = computed<TripAirport | null>(() => {
    const airports = activeLocation.value?.airports ?? [];
    const code = selectedAirportCodes.value[activeIndex.value];

    return (
        airports.find((airport) => airport.iata_code === code) ??
        airports[0] ??
        null
    );
});
const canChooseAirport = computed(
    () =>
        transfersEnabled.value &&
        (activeLocation.value?.airports.length ?? 0) > 1,
);
const activeTransferKey = computed(() =>
    selectedAirport.value
        ? transferKey(activeIndex.value, selectedAirport.value.iata_code)
        : null,
);
const activeTransfer = computed(() =>
    activeTransferKey.value
        ? (transfers.states.value[activeTransferKey.value] ?? null)
        : null,
);

watch(
    activeTransferKey,
    (key) => {
        if (key && transfersEnabled.value) {
            transfers.ensureLoaded(key);
        }
    },
    { immediate: true },
);

function selectAirport(airport: TripAirport): void {
    selectedAirportCodes.value = {
        ...selectedAirportCodes.value,
        [activeIndex.value]: airport.iata_code,
    };
}

const mapPlace = ref<MapPlace | null>(null);
const mapOpen = ref(false);

/** The route from the selected airport into the place, on the map. */
function showTransferOnMap(): void {
    const airport = selectedAirport.value;
    const location = activeLocation.value;

    if (!airport || !location) {
        return;
    }

    mapPlace.value = {
        name: location.label,
        description: `From ${airport.name} (${airport.iata_code})`,
        lat: location.lat,
        lng: location.lng,
        origin: {
            label: `${airport.iata_code} airport`,
            lat: airport.lat,
            lng: airport.lng,
        },
        travelMode: 'driving',
    };
    mapOpen.value = true;
}

function retryTransfer(): void {
    if (activeTransferKey.value) {
        void transfers.load(activeTransferKey.value);
    }
}

function airportPlace(airport: TripAirport): string {
    return airport.municipality && !airport.name.includes(airport.municipality)
        ? `${airport.name}, ${airport.municipality}`
        : airport.name;
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div
            class="h-1.5 bg-gradient-to-r from-sky-400 via-cyan-500 to-teal-500"
        />
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-lg font-bold">
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400"
                >
                    <Plane class="size-4" />
                </span>
                Airports
            </CardTitle>
            <p
                v-if="activeLocation && locations.length === 1"
                class="text-sm text-muted-foreground"
            >
                Nearest airports to {{ activeLocation.label }} and how to get
                there from the airport
            </p>

            <TripHubPlaceTabs
                v-if="locations.length > 1"
                v-model:active="activeIndex"
                :labels="locations.map((location) => location.label)"
            />
        </CardHeader>

        <CardContent class="space-y-6">
            <div v-if="isLoading" class="space-y-3">
                <Skeleton v-for="n in 4" :key="n" class="h-14 w-full" />
            </div>

            <template v-else>
                <div
                    v-if="departure && departure.airports.length > 0"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-border/60 bg-muted/20 px-3 py-2 text-sm"
                >
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <PlaneTakeoff class="size-4 text-sky-600" />
                        Flying out of {{ departure.label }}
                    </span>
                    <span
                        v-for="airport in departure.airports.slice(0, 2)"
                        :key="airport.iata_code"
                        class="text-muted-foreground"
                    >
                        <span class="font-semibold text-foreground">
                            {{ airport.iata_code }}
                        </span>
                        · {{ formatKm(airport.distance_km) }}
                    </span>
                </div>

                <template v-if="activeLocation">
                    <section class="space-y-3">
                        <h3
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <Plane class="size-4 text-muted-foreground" />
                            Nearest airports
                        </h3>
                        <p
                            v-if="canChooseAirport"
                            class="text-sm text-muted-foreground"
                        >
                            Select an airport to see how to get from it to
                            {{ activeLocation.label }}.
                        </p>

                        <ul
                            v-if="activeLocation.airports.length > 0"
                            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <li
                                v-for="airport in activeLocation.airports"
                                :key="airport.iata_code"
                            >
                                <component
                                    :is="canChooseAirport ? 'button' : 'div'"
                                    :type="
                                        canChooseAirport ? 'button' : undefined
                                    "
                                    :aria-pressed="
                                        canChooseAirport
                                            ? selectedAirport?.iata_code ===
                                              airport.iata_code
                                            : undefined
                                    "
                                    :class="
                                        cn(
                                            'flex h-full w-full items-start gap-3 rounded-xl border p-3 text-left transition-colors',
                                            canChooseAirport &&
                                                selectedAirport?.iata_code ===
                                                    airport.iata_code
                                                ? 'border-sky-500 bg-sky-500/10 ring-1 ring-sky-500'
                                                : 'border-border/60 bg-muted/20',
                                            canChooseAirport &&
                                                selectedAirport?.iata_code !==
                                                    airport.iata_code &&
                                                'cursor-pointer hover:border-sky-500/60 hover:bg-muted/40',
                                        )
                                    "
                                    @click="
                                        canChooseAirport &&
                                        selectAirport(airport)
                                    "
                                >
                                    <span
                                        class="flex h-9 min-w-12 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 px-2 font-mono text-sm font-bold text-sky-700 dark:text-sky-300"
                                    >
                                        {{ airport.iata_code }}
                                    </span>
                                    <div class="min-w-0 space-y-1 break-words">
                                        <p
                                            class="text-sm leading-snug font-medium"
                                        >
                                            {{ airportPlace(airport) }}
                                        </p>
                                        <div
                                            class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <span>
                                                {{
                                                    formatKm(
                                                        airport.distance_km,
                                                    )
                                                }}
                                                away
                                            </span>
                                            <Badge
                                                v-if="airport.is_major"
                                                variant="outline"
                                                class="font-normal"
                                            >
                                                Major airport
                                            </Badge>
                                        </div>
                                    </div>
                                </component>
                            </li>
                        </ul>

                        <p v-else class="text-sm text-muted-foreground">
                            No airports with scheduled flights found near
                            {{ activeLocation.label }}.
                        </p>
                    </section>

                    <template v-if="transfersEnabled && selectedAirport">
                        <div
                            v-if="activeTransfer?.error"
                            class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
                        >
                            <span>{{ activeTransfer.error }}</span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="retryTransfer"
                            >
                                Try again
                            </Button>
                        </div>

                        <div
                            v-else-if="!activeTransfer?.data"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <Skeleton
                                v-for="n in 2"
                                :key="n"
                                class="h-24 w-full"
                            />
                        </div>

                        <section
                            v-else-if="activeTransfer.data.transfer"
                            class="space-y-3"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <Car class="size-4 text-muted-foreground" />
                                From
                                {{
                                    activeTransfer.data.transfer.airport
                                        .iata_code
                                }}
                                airport to {{ activeLocation.label }}
                            </h3>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="space-y-1 rounded-xl border border-border/60 bg-muted/20 p-3"
                                >
                                    <p
                                        class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        <Car class="size-3.5" />
                                        Taxi or car
                                    </p>
                                    <template
                                        v-if="
                                            activeTransfer.data.transfer.drive
                                        "
                                    >
                                        <p class="text-lg font-semibold">
                                            ~{{
                                                formatMinutes(
                                                    activeTransfer.data.transfer
                                                        .drive.minutes,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatKm(
                                                    activeTransfer.data.transfer
                                                        .drive.distance_km,
                                                )
                                            }}
                                            by road, without traffic
                                        </p>
                                    </template>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        No driving estimate available.
                                    </p>
                                </div>

                                <div
                                    class="space-y-1 rounded-xl border border-border/60 bg-muted/20 p-3"
                                >
                                    <p
                                        class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        <Bus class="size-3.5" />
                                        Public transport
                                    </p>
                                    <template
                                        v-if="
                                            activeTransfer.data.transfer.transit
                                        "
                                    >
                                        <p class="text-lg font-semibold">
                                            ~{{
                                                formatMinutes(
                                                    activeTransfer.data.transfer
                                                        .transit.minutes,
                                                )
                                            }}
                                        </p>
                                        <ul
                                            v-if="
                                                activeTransfer.data.transfer
                                                    .transit.rides.length > 0
                                            "
                                            class="space-y-1 text-xs text-muted-foreground"
                                        >
                                            <li
                                                v-for="(
                                                    ride, rideIndex
                                                ) in activeTransfer.data
                                                    .transfer.transit.rides"
                                                :key="rideIndex"
                                                class="break-words"
                                            >
                                                <span
                                                    class="font-semibold text-foreground"
                                                >
                                                    {{ ride.line }}
                                                </span>
                                                <template v-if="ride.toward">
                                                    towards {{ ride.toward }}
                                                </template>
                                                <template v-if="ride.stops">
                                                    · {{ ride.stops }}
                                                    {{
                                                        ride.stops === 1
                                                            ? 'stop'
                                                            : 'stops'
                                                    }}
                                                </template>
                                                ·
                                                {{
                                                    formatMinutes(ride.minutes)
                                                }}
                                            </li>
                                        </ul>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            Mostly on foot.
                                        </p>
                                    </template>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        No public transport route found. Check
                                        directions for the latest options.
                                    </p>
                                </div>
                            </div>

                            <Button
                                v-if="selectedAirport"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showTransferOnMap"
                            >
                                <Route class="size-4" />
                                Show route on map
                            </Button>
                        </section>
                    </template>
                </template>

                <p class="text-xs text-muted-foreground">
                    Airports from OurAirports. Transfer times from Geoapify and
                    OpenStreetMap — typical times without traffic, not live
                    timetables.
                </p>
            </template>
        </CardContent>

        <TripHubMapDialog v-model:open="mapOpen" :place="mapPlace" />
    </Card>
</template>
