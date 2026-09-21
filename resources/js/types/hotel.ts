export type TripHotel = {
    name: string;
    address: string | null;
    lat: number | null;
    lng: number | null;
    distance_meters: number | null;
    place_id: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    stars: number | null;
    rooms: number | null;
    facilities: string[];
    opening_hours: string | null;
};

export type TripHotelsPage = {
    available: boolean;
    hotels: TripHotel[] | null;
    has_more: boolean;
    next_offset: number;
    message?: string;
};

export type TripHotelsLocation = TripHotelsPage & {
    label: string;
};

export type TripHotels = {
    locations: TripHotelsLocation[];
};
