export type HomeStats = {
    open_trips: number;
    destinations: number;
    travelers: number;
    trips_planned: number;
};

export type HomeOpenTrip = {
    id: string;
    title: string;
    type: string;
    type_label: string;
    destination: { label: string } | null;
    start_date: string | null;
    end_date: string | null;
    cover_image_thumb_url: string | null;
    is_past: boolean;
    is_joinable: boolean;
    organizer: { name: string } | null;
    group: {
        category: string | null;
        difficulty: string | null;
        max_group_size: number | null;
        seats_left: number | null;
        member_count: number;
        cost_model_label: string | null;
        cost_amount: number | null;
        cost_currency: string | null;
    };
};

export type HomeDestination = {
    label: string;
    name: string;
    region: string | null;
    trip_count: number;
    cover_image_url: string | null;
};

export type HomeCategory = {
    value: string;
    count: number;
};

export type HomePersonalTrip = {
    id: string;
    title: string;
    type: string;
    type_label: string;
    destination: string | null;
    start_date: string | null;
    end_date: string | null;
    cover_image_thumb_url: string | null;
    is_owner: boolean;
    is_public: boolean;
};

export type HomeMyTrips = {
    counts: { upcoming: number; ongoing: number; past: number };
    upcoming: HomePersonalTrip[];
    past: HomePersonalTrip[];
};

export const openTripCategoryLabels: Record<string, string> = {
    trek: 'Trek',
    bike: 'Bike',
    road_trip: 'Road trip',
    vacation: 'Vacation',
    adventure: 'Adventure',
    leisure: 'Leisure',
    spiritual: 'Spiritual',
    cultural: 'Cultural',
    wildlife: 'Wildlife',
    backpacking: 'Backpacking',
    weekend_getaway: 'Weekend getaway',
    other: 'Other',
};

export function openTripCategoryLabel(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return openTripCategoryLabels[value] ?? value.replace(/_/g, ' ');
}
