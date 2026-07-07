export type TripWeatherDay = {
    date: string;
    temperature_min: number;
    temperature_max: number;
    precipitation_mm: number;
    weather_code: number;
    weather_label: string;
    weather_kind: 'clear' | 'cloudy' | 'fog' | 'rain' | 'snow' | 'storm';
};

export type TripWeather = {
    available: boolean;
    reason?: 'missing_coordinates' | 'missing_dates' | 'past_trip' | 'driver_disabled' | 'fetch_failed';
    message?: string;
    mode?: 'forecast' | 'typical';
    mode_label?: string;
    location_label?: string | null;
    period_label?: string;
    summary?: string;
    days?: TripWeatherDay[];
    temperature_min?: number;
    temperature_max?: number;
    avg_daily_precipitation_mm?: number;
    rainy_day_percent?: number;
    sample_years?: number;
    disclaimer?: string;
    source?: string;
};
