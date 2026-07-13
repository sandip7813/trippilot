export type TripWeatherDay = {
    date: string;
    temperature_min: number;
    temperature_max: number;
    precipitation_mm: number;
    weather_code: number;
    weather_label: string;
    weather_kind: 'clear' | 'cloudy' | 'fog' | 'rain' | 'snow' | 'storm';
};

export type TripWeatherTypical = {
    period_label: string;
    summary: string;
    temperature_min: number;
    temperature_max: number;
    avg_daily_precipitation_mm: number;
    rainy_day_percent: number;
    sample_years?: number;
};

export type TripWeather = {
    available: boolean;
    reason?:
        | 'missing_coordinates'
        | 'missing_dates'
        | 'past_trip'
        | 'driver_disabled'
        | 'fetch_failed';
    message?: string;
    mode?: 'forecast' | 'typical' | 'mixed' | 'multi_city';
    mode_label?: string;
    location_label?: string | null;
    period_label?: string;
    summary?: string;
    days?: TripWeatherDay[];
    forecast_days?: TripWeatherDay[];
    forecast_range_label?: string;
    typical_remainder?: TripWeatherTypical;
    remainder_period_label?: string;
    trip_end_date?: string;
    temperature_min?: number;
    temperature_max?: number;
    avg_daily_precipitation_mm?: number;
    rainy_day_percent?: number;
    sample_years?: number;
    disclaimer?: string;
    source?: string;
    segments?: TripWeatherSegment[];
};

export type TripWeatherSegment = TripWeather & {
    segment_label?: string | null;
    sequence?: number | null;
    date_from?: string | null;
    date_to?: string | null;
    nights?: number | null;
};
