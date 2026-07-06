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

export type TripLocation = {
    label: string | null;
    lat: number | null;
    lng: number | null;
    place_id?: string | null;
};

export type TripItinerary = {
    days: Array<{
        day: number;
        date?: string;
        title?: string;
        activities?: Array<{
            time?: string;
            title: string;
            notes?: string;
        }>;
    }>;
    summary?: string;
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
    start_date: string | null;
    end_date: string | null;
    budget: number | null;
    travelers: number;
    status: TripStatus;
    status_label: string;
    is_favorite: boolean;
    notes: string | null;
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

export function locationLabel(location: TripLocation | null | undefined): string | null {
    return location?.label ?? null;
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
