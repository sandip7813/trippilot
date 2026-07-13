export type TrainStation = {
    code: string;
    name: string;
};

export type TripTrainLiveStatus = {
    type: string | null;
    platform: string | null;
    delay_minutes: number | null;
    expected_arrival: string | null;
};

export type TripTrainOption = {
    number: string;
    name: string;
    type: string | null;
    category: string | null;
    departure: string | null;
    arrival: string | null;
    departure_day: number | null;
    arrival_day: number | null;
    day_offset: number | null;
    from_sequence: number | null;
    to_sequence: number | null;
    duration_minutes: number | null;
    duration_label: string | null;
    run_days: string[];
    runs_daily: boolean;
    distance_km: number | null;
    total_halts_between: number | null;
    travel_date: string | null;
    from_station: TrainStation;
    to_station: TrainStation;
    live: TripTrainLiveStatus | null;
};

export type TripTrainRailhead = {
    place_key: string;
    place_label: string;
    station: TrainStation;
    last_mile: string;
};

export type TripTrainLeg = {
    sequence?: number;
    direction: 'outbound' | 'return' | 'inter_city';
    date: string | null;
    from_station: TrainStation;
    to_station: TrainStation;
    from_label?: string | null;
    to_label?: string | null;
    route_label: string;
    available: boolean;
    reason?: 'no_trains' | 'fetch_failed';
    message?: string;
    count: number;
    trains: TripTrainOption[];
    search_mode?: 'direct' | 'railhead';
    railhead?: TripTrainRailhead;
};

export type TripTrainHalt = {
    sequence: number | null;
    code: string;
    name: string;
    arrival: string | null;
    departure: string | null;
    day: number | null;
    distance_km: number | null;
    platform: string | null;
    halt_minutes: number | null;
    is_halt: boolean;
    is_boarding?: boolean;
    is_alighting?: boolean;
};

export type TripTrainHaltsResponse = {
    available: boolean;
    message?: string;
    train_number?: string;
    from_code?: string;
    to_code?: string;
    travel_date?: string | null;
    halt_count?: number;
    halts?: TripTrainHalt[];
};

export type TripTrainTimings = {
    available: boolean;
    reason?:
        | 'driver_disabled'
        | 'not_domestic'
        | 'missing_coordinates'
        | 'stations_unresolved'
        | 'same_station'
        | 'no_trains'
        | 'fetch_failed';
    message?: string;
    from_station?: TrainStation;
    to_station?: TrainStation;
    origin_label?: string | null;
    destination_label?: string | null;
    route_mode?: 'simple' | 'multi_city';
    route_label?: string | null;
    leg_count?: number;
    legs?: TripTrainLeg[];
    outbound?: TripTrainLeg;
    return?: TripTrainLeg;
    destination_railhead?: TripTrainRailhead | null;
    direct_only_notice?: string;
    uses_railhead_fallback?: boolean;
    disclaimer?: string;
    source?: string;
};
