export type TripType = 'vacation' | 'road';

export type TripStatus = 'draft' | 'planned' | 'archived';

export type TravelStyle =
    | 'family'
    | 'business'
    | 'adventure'
    | 'romantic'
    | 'backpacking'
    | 'solo'
    | 'group'
    | 'pilgrimage'
    | 'cruise'
    | 'weekend';

export type TripScope = 'domestic' | 'international';

export type TripRouteMode = 'simple' | 'multi_city';

export type TripLocation = {
    label: string | null;
    lat: number | null;
    lng: number | null;
    place_id?: string | null;
    country_code?: string | null;
};

export type TripWaypoint = {
    sequence: number;
    location: TripLocation | null;
    nights?: number | null;
    notes?: string | null;
};

export type TripRouteStopKind = 'origin' | 'stay' | 'return';

export type TripRouteStop = {
    kind: TripRouteStopKind;
    sequence: number;
    label?: string | null;
    nights?: number | null;
    arrival_date?: string | null;
    departure_date?: string | null;
};

export type TripRouteSummary = {
    route_mode: TripRouteMode;
    returns_to_origin: boolean;
    city_count: number;
    stop_count: number;
    leg_count: number;
    route_points: string[];
    route_display_points?: string[];
    route_label: string;
    route_stops?: TripRouteStop[];
    stay_segments?: Array<{
        sequence: number;
        label?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        nights?: number | null;
    }>;
};

export type TripTemplate = {
    key: string;
    label: string;
    description: string;
    returns_to_origin: boolean;
    suggested_nights: number[];
    waypoint_hints: string[];
};

export type TripItinerary = {
    days: Array<{
        day: number;
        date?: string | null;
        title?: string;
        city?: string | null;
        waypoint_sequence?: number | null;
        activities?: Array<{
            time?: string | null;
            title: string;
            notes?: string | null;
            city?: string | null;
            kind?: string | null;
        }>;
    }>;
    summary?: string;
    packing_list?: string[];
    budget_breakdown?: Record<string, unknown>;
};

export type Trip = {
    id: string;
    user_id: number;
    type: TripType;
    type_label: string;
    travel_style: TravelStyle | null;
    travel_style_label: string | null;
    title: string;
    origin: TripLocation | null;
    destination: TripLocation | null;
    route_mode?: TripRouteMode;
    waypoints?: TripWaypoint[];
    returns_to_origin?: boolean;
    route_summary?: TripRouteSummary | null;
    trip_scope: TripScope | null;
    trip_scope_label: string | null;
    start_date: string | null;
    end_date: string | null;
    budget: number | null;
    travelers: number;
    status: TripStatus;
    status_label: string;
    is_favorite: boolean;
    notes: string | null;
    cover_image_url?: string | null;
    cover_image_thumb_url?: string | null;
    cover_image_version?: number;
    cover_image_source?: string | null;
    cover_image_source_label?: string | null;
    cover_image_exhausted?: boolean;
    cover_image_attribution?: {
        source?: string | null;
        author?: string | null;
        license?: string | null;
        credit_url?: string | null;
        description?: string | null;
    } | null;
    itinerary: TripItinerary;
    created_at: string | null;
    updated_at: string | null;
};

export type TripOption = {
    value: string;
    label: string;
};

export type TripFilter = 'all' | 'favorites' | 'archived';

export type TripCounts = {
    all: number;
    favorites: number;
    archived: number;
};

export function locationLabel(
    location: TripLocation | null | undefined,
): string | null {
    return location?.label ?? null;
}

export function locationHasCoordinates(
    location: TripLocation | null | undefined,
): boolean {
    return location?.lat != null && location?.lng != null;
}

export function openStreetMapUrl(lat: number, lng: number): string {
    return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=12/${lat}/${lng}`;
}

export function locationRouteLabel(
    origin: TripLocation | null | undefined,
    destination: TripLocation | null | undefined,
): string {
    const from = locationLabel(origin);
    const to = locationLabel(destination);

    if (from && to) {
        return `${from} → ${to}`;
    }

    return to ?? from ?? 'Route not set';
}
