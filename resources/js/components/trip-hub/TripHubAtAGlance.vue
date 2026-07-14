<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Calendar, MapPin, Train, Users, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import TripHubRouteStopsList from '@/components/trip-hub/TripHubRouteStopsList.vue';
import TripRouteMapDialog from '@/components/trip-hub/TripRouteMapDialog.vue';
import { Button } from '@/components/ui/button';
import {
    shortCityLabel,
    useTripRouteStops,
} from '@/composables/useTripRouteStops';
import { formatDisplayDateRange } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import type { TripTrainTimings } from '@/types/train';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
    trainTimings?: TripTrainTimings | null;
}>();

const page = usePage();
const mapOpen = ref(false);

const appCurrency = computed(
    () =>
        (page.props.currency as
            { code?: string; locale?: string } | undefined) ?? {
            code: 'INR',
            locale: 'en-IN',
        },
);

const formattedBudget = computed(() => {
    if (props.trip.budget == null) {
        return '—';
    }

    return formatMoney(props.trip.budget, {
        currency: appCurrency.value.code ?? 'INR',
        locale: appCurrency.value.locale ?? 'en-IN',
    });
});

const dateRange = computed(() =>
    formatDisplayDateRange(props.trip.start_date, props.trip.end_date),
);

const transportHint = computed((): string => {
    if (props.trip.route_mode === 'multi_city') {
        const legs =
            props.trainTimings?.leg_count ??
            props.trip.route_summary?.leg_count ??
            0;
        const cities = props.trip.route_summary?.stop_count ?? 0;

        if (cities > 0) {
            return `${cities} cities · ${legs} travel legs below itinerary`;
        }

        return 'Multi-city route below itinerary';
    }

    if (props.trip.trip_scope === 'domestic') {
        if (props.trainTimings?.available) {
            const legCount =
                props.trainTimings.leg_count ??
                props.trainTimings.legs?.length ??
                0;
            const outboundCount = props.trainTimings.outbound?.count ?? 0;
            const returnCount = props.trainTimings.return?.count ?? 0;
            const total = legCount > 0 ? legCount : outboundCount + returnCount;

            if (total > 0) {
                if (legCount > 2) {
                    return `${legCount} train legs below itinerary`;
                }

                const parts = [];

                if (outboundCount > 0) {
                    parts.push(
                        `${outboundCount} outbound train${outboundCount === 1 ? '' : 's'}`,
                    );
                }

                if (returnCount > 0) {
                    parts.push(
                        `${returnCount} return train${returnCount === 1 ? '' : 's'}`,
                    );
                }

                return `${parts.join(' · ')} below itinerary`;
            }
        }

        if (props.trainTimings?.message) {
            return props.trainTimings.message;
        }

        return 'Indian rail options below itinerary';
    }

    if (props.trip.trip_scope === 'international') {
        return 'Flights & local transit (coming soon)';
    }

    return 'Set a mapped destination';
});

const { routeChainLabels, routeStops, routeMapPoints } = useTripRouteStops({
    trip: props.trip,
    routeSummary: props.trip.route_summary,
});

const showFullRoute = computed(() => routeStops.value.length >= 2);

const canShowMap = computed(() => routeMapPoints.value.length > 0);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm sm:col-span-2"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-sky-500/15 text-sky-600 dark:text-sky-400"
            >
                <Calendar class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Dates
                </p>
                <p class="text-sm leading-snug font-semibold">
                    {{ dateRange }}
                </p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-violet-500/15 text-violet-600 dark:text-violet-400"
            >
                <Users class="size-4" />
            </span>
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Travelers
                </p>
                <p class="text-sm font-semibold">{{ trip.travelers }}</p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
            >
                <Wallet class="size-4" />
            </span>
            <div class="min-w-0">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Budget
                </p>
                <p class="text-sm leading-snug font-semibold">
                    {{ formattedBudget }}
                </p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm sm:col-span-2"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400"
            >
                <Train class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Transport
                </p>
                <p class="text-sm leading-snug text-muted-foreground">
                    {{ transportHint }}
                </p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm sm:col-span-2"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-teal-500/15 text-teal-600 dark:text-teal-400"
            >
                <MapPin class="size-4" />
            </span>
            <div class="min-w-0 flex-1 text-sm leading-snug">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Route
                    </p>
                    <Button
                        v-if="canShowMap"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-7 shrink-0 px-2.5 text-xs"
                        @click="mapOpen = true"
                    >
                        <MapPin class="mr-1.5 size-3.5" />
                        View map
                    </Button>
                </div>
                <div v-if="showFullRoute" class="mt-2 space-y-2">
                    <div
                        v-if="routeChainLabels.length > 0"
                        class="flex flex-wrap items-center gap-x-1 gap-y-0.5 text-[11px] text-muted-foreground"
                    >
                        <template
                            v-for="(label, index) in routeChainLabels"
                            :key="`route-chain-${index}-${label}`"
                        >
                            <span>{{ shortCityLabel(label) }}</span>
                            <span v-if="index < routeChainLabels.length - 1"
                                >→</span
                            >
                        </template>
                    </div>
                    <TripHubRouteStopsList :stops="routeStops" compact />
                </div>
                <div v-else class="mt-1 space-y-0.5">
                    <p v-if="locationLabel(trip.origin)">
                        <span class="text-muted-foreground">From</span>
                        {{ locationLabel(trip.origin) }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">To</span>
                        {{ locationLabel(trip.destination) ?? 'Not set' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <TripRouteMapDialog v-model:open="mapOpen" :trip="trip" />
</template>
