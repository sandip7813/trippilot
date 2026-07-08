import type { Trip, TripLocation, TripOption } from './trip';

export type RoadProfile = {
    vehicle_class: string;
    fuel_type: string;
    driving_pace?: string | null;
    food_preference?: string | null;
    avoid_tolls?: boolean;
    avoid_highways?: boolean;
    ev_range_km?: number | null;
    max_drive_hours_per_day?: number | null;
};

export type RoadTripRoute = {
    distance_km: number;
    duration_seconds: number;
    has_tolls: boolean;
    polyline: Array<[number, number]>;
    legs?: unknown[];
    computed_at?: string;
};

export type RoadTripStop = {
    label: string;
    lat: number;
    lng: number;
    place_id?: string | null;
    kind?: string;
    notes?: string;
    source?: string;
};

export type RoadTripBreak = {
    id: string;
    kind: string;
    title: string;
    reason: string;
    sequence: number;
    label: string;
    lat: number;
    lng: number;
    place_id?: string | null;
    category?: string;
    address?: string | null;
    source?: string;
};

export type RoadTripPlace = {
    name: string;
    lat: number;
    lng: number;
    place_id?: string | null;
    category?: string;
    address?: string | null;
};

export type AmenitiesCache = Record<
    string,
    {
        places: RoadTripPlace[];
        fetched_at: string;
    }
>;

export type RoadTrip = Trip & {
    road_profile: RoadProfile | null;
    stops: RoadTripStop[];
    route: RoadTripRoute | null;
    suggested_breaks: RoadTripBreak[];
    amenities_cache: AmenitiesCache | null;
};

export type RoadTripFormOptions = {
    vehicleClasses: TripOption[];
    fuelTypes: TripOption[];
    drivingPaces: TripOption[];
    foodPreferences: TripOption[];
};

export const amenityLayerLabels: Record<string, string> = {
    fuel: 'Fuel stations',
    ev: 'EV charging',
    hotels: 'Hotels',
    food: 'Food',
    parking: 'Parking',
    pharmacy: 'Pharmacies',
    viewpoints: 'Viewpoints',
};

export function formatDrivingDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);

    if (hours === 0) {
        return `${minutes} min`;
    }

    if (minutes === 0) {
        return `${hours} hr`;
    }

    return `${hours} hr ${minutes} min`;
}

export function formatDrivingDistance(km: number): string {
    return `${km.toLocaleString(undefined, { maximumFractionDigits: 1 })} km`;
}

export function breakKindLabel(kind: string): string {
    const labels: Record<string, string> = {
        fuel: 'Fuel',
        meal: 'Meal',
        rest: 'Rest',
        overnight: 'Overnight',
        scenic: 'Scenic',
        break: 'Break',
    };

    return labels[kind] ?? kind;
}

export function locationCoordinates(
    location: TripLocation | null | undefined,
): [number, number] | null {
    if (location?.lat == null || location?.lng == null) {
        return null;
    }

    return [location.lat, location.lng];
}
