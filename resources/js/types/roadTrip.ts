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
    address?: string | null;
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
    route_zone?: 'en_route' | 'destination';
};

export function amenityRouteZoneLabel(
    zone: RoadTripPlace['route_zone'],
): string | null {
    if (zone === 'en_route') {
        return 'En route';
    }

    if (zone === 'destination') {
        return 'Destination';
    }

    return null;
}

export function amenityPlaceKey(place: RoadTripPlace): string {
    return place.place_id ?? `${place.lat}:${place.lng}:${place.name}`;
}

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
    toilets: 'Restrooms',
    supermarkets: 'Supermarkets & stores',
    atm: 'ATMs',
    parking: 'Parking',
    pharmacy: 'Pharmacies',
    hospitals: 'Hospitals & clinics',
    mechanics: 'Mechanics & garages',
    tyres: 'Tyre shops',
    rest_areas: 'Rest areas',
    emergency: 'Police & emergency',
    viewpoints: 'Viewpoints',
    bike: 'Bike shops & repair',
};

export type AmenityLayerStyle = {
    color: string;
    glyph: string;
};

export const amenityLayerStyles: Record<string, AmenityLayerStyle> = {
    fuel: { color: '#ea580c', glyph: '⛽' },
    ev: { color: '#16a34a', glyph: '⚡' },
    hotels: { color: '#7c3aed', glyph: '🏨' },
    food: { color: '#dc2626', glyph: '🍽' },
    toilets: { color: '#0284c7', glyph: '🚻' },
    supermarkets: { color: '#d97706', glyph: '🛒' },
    atm: { color: '#059669', glyph: '🏧' },
    parking: { color: '#2563eb', glyph: 'P' },
    pharmacy: { color: '#db2777', glyph: '✚' },
    hospitals: { color: '#e11d48', glyph: '🏥' },
    mechanics: { color: '#475569', glyph: '🔧' },
    tyres: { color: '#64748b', glyph: '🛞' },
    rest_areas: { color: '#84cc16', glyph: '☕' },
    emergency: { color: '#b91c1c', glyph: '🚨' },
    viewpoints: { color: '#0891b2', glyph: '◎' },
    bike: { color: '#0d9488', glyph: '🚲' },
};

export function amenityLayerStyle(layer: string | null | undefined): AmenityLayerStyle {
    if (layer && amenityLayerStyles[layer]) {
        return amenityLayerStyles[layer];
    }

    return { color: '#6366f1', glyph: '•' };
}

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

export function breakDisplayReason(reason: string | null | undefined): string | null {
    const trimmed = reason?.trim();

    if (! trimmed || trimmed === 'Suggested stop along your route.') {
        return null;
    }

    return trimmed;
}

export function stopDisplayAddress(stop: RoadTripStop): string | null {
    const address = stop.address?.trim();

    if (address) {
        return address;
    }

    return null;
}

export function hasCalculatedRoute(
    route: RoadTripRoute | null | undefined,
): boolean {
    if (!route) {
        return false;
    }

    if ((route.polyline?.length ?? 0) >= 2) {
        return true;
    }

    return (route.distance_km ?? 0) > 0 && (route.duration_seconds ?? 0) > 0;
}

export function locationCoordinates(
    location: TripLocation | null | undefined,
): [number, number] | null {
    if (location?.lat == null || location?.lng == null) {
        return null;
    }

    return [location.lat, location.lng];
}
