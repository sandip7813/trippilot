import { computed, type ComputedRef } from 'vue';
import type { Trip, TripRouteStop, TripRouteSummary } from '@/types/trip';

type UseTripRouteStopsOptions = {
    trip: Trip;
    routeSummary?: TripRouteSummary | null;
    originLabel?: string | null;
};

type UseTripRouteStopsReturn = {
    originLabel: ComputedRef<string | null>;
    routeChainLabels: ComputedRef<string[]>;
    routeStops: ComputedRef<TripRouteStop[]>;
    hasMultiStopRoute: ComputedRef<boolean>;
};

export function useTripRouteStops(
    options: UseTripRouteStopsOptions,
): UseTripRouteStopsReturn {
    const originLabel = computed(
        () => options.originLabel ?? options.trip.origin?.label ?? null,
    );

    const routeChainLabels = computed(() => {
        const fromSummary = options.routeSummary?.route_display_points ?? [];

        if (fromSummary.length > 0) {
            return fromSummary;
        }

        return buildChainFromTrip(
            options.trip,
            originLabel.value,
            options.routeSummary?.returns_to_origin,
        );
    });

    const routeStops = computed((): TripRouteStop[] => {
        const chain = routeChainLabels.value;

        if (chain.length === 0) {
            return [];
        }

        const backendStops = options.routeSummary?.route_stops ?? [];

        const segmentsByLabel = new Map(
            (options.routeSummary?.stay_segments ?? [])
                .filter((segment) => segment.label)
                .map((segment) => [normalizeLabel(segment.label!), segment]),
        );

        const stops: TripRouteStop[] = [];

        chain.forEach((label, index) => {
            const isOrigin = index === 0;
            const isReturn =
                !isOrigin &&
                options.routeSummary?.returns_to_origin !== false &&
                options.trip.returns_to_origin !== false &&
                index === chain.length - 1 &&
                originLabel.value !== null &&
                normalizeLabel(label) === normalizeLabel(originLabel.value);

            const existing = backendStops[index];
            const segment = segmentsByLabel.get(normalizeLabel(label));

            if (isOrigin) {
                stops.push({
                    kind: 'origin',
                    sequence: 0,
                    label,
                    nights: null,
                    arrival_date: null,
                    departure_date:
                        existing?.departure_date ??
                        options.trip.start_date ??
                        segment?.date_from ??
                        null,
                });

                return;
            }

            if (isReturn) {
                stops.push({
                    kind: 'return',
                    sequence: stops.length,
                    label,
                    nights: null,
                    arrival_date:
                        existing?.arrival_date ??
                        options.trip.end_date ??
                        null,
                    departure_date: null,
                });

                return;
            }

            stops.push({
                kind: 'stay',
                sequence: stops.length,
                label,
                nights: existing?.nights ?? segment?.nights ?? null,
                arrival_date:
                    existing?.arrival_date ?? segment?.date_from ?? null,
                departure_date:
                    existing?.departure_date ??
                    (segment?.date_to ? addDayIso(segment.date_to) : null),
            });
        });

        return stops;
    });

    const hasMultiStopRoute = computed(
        () =>
            (options.trip.waypoints?.length ?? 0) > 0 ||
            options.trip.route_mode === 'multi_city' ||
            routeStops.value.length > 2,
    );

    return {
        originLabel,
        routeChainLabels,
        routeStops,
        hasMultiStopRoute,
    };
}

function normalizeLabel(label: string): string {
    return label.trim().toLowerCase();
}

function buildChainFromTrip(
    trip: Trip,
    origin: string | null,
    returnsToOriginFromSummary?: boolean,
): string[] {
    const chain: string[] = [];

    if (origin) {
        chain.push(origin);
    }

    for (const waypoint of trip.waypoints ?? []) {
        const label = waypoint.location?.label;

        if (label) {
            chain.push(label);
        }
    }

    const destination = trip.destination?.label;

    if (
        destination &&
        chain[chain.length - 1] !== destination &&
        !chain.some(
            (label) => normalizeLabel(label) === normalizeLabel(destination),
        )
    ) {
        chain.push(destination);
    }

    const returnsHome =
        returnsToOriginFromSummary ?? trip.returns_to_origin ?? true;

    if (
        returnsHome &&
        origin &&
        normalizeLabel(chain[chain.length - 1] ?? '') !== normalizeLabel(origin)
    ) {
        chain.push(origin);
    }

    return dedupeConsecutiveLabels(chain);
}

function dedupeConsecutiveLabels(labels: string[]): string[] {
    return labels.filter((label, index) => {
        if (index === 0) {
            return true;
        }

        return (
            normalizeLabel(label) !== normalizeLabel(labels[index - 1] ?? '')
        );
    });
}

function addDayIso(isoDate: string): string {
    const date = new Date(`${isoDate}T00:00:00`);

    date.setDate(date.getDate() + 1);

    return date.toISOString().slice(0, 10);
}

export function stopKindLabel(stop: TripRouteStop): string {
    if (stop.kind === 'origin') {
        return 'Origin';
    }

    if (stop.kind === 'return') {
        return 'Return';
    }

    return 'Stay';
}

export function nightsLabel(nights: number | null | undefined): string | null {
    if (nights == null || nights <= 0) {
        return null;
    }

    return `${nights} night${nights === 1 ? '' : 's'}`;
}

export function shortCityLabel(label: string | null | undefined): string {
    if (!label) {
        return 'Stop';
    }

    return label.split(',')[0]?.trim() || label;
}
