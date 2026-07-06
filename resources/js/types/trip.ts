export type TripType = 'vacation' | 'road';

export type TripStatus = 'draft' | 'planned' | 'archived';

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
    title: string;
    destination: string | null;
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
