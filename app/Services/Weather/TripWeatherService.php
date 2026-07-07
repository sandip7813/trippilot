<?php

namespace App\Services\Weather;

use App\Models\Trip;
use App\Services\Weather\OpenMeteo\OpenMeteoClient;
use App\Services\Weather\OpenMeteo\WeatherCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TripWeatherService
{
    private const int FORECAST_HORIZON_DAYS = 16;

    private const int TYPICAL_YEARS = 10;

    public function __construct(private OpenMeteoClient $client) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forTrip(Trip $trip): ?array
    {
        if (config('integrations.weather.driver') !== 'open_meteo') {
            return [
                'available' => false,
                'reason' => 'driver_disabled',
                'message' => 'Set WEATHER_DRIVER=open_meteo in your .env file to enable weather.',
            ];
        }

        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));

        if ($destination === null || $destination['lat'] === null || $destination['lng'] === null) {
            return [
                'available' => false,
                'reason' => 'missing_coordinates',
                'message' => 'Pick a destination from search to enable weather insights.',
            ];
        }

        if ($trip->start_date === null) {
            return [
                'available' => false,
                'reason' => 'missing_dates',
                'message' => 'Add trip dates to see weather for your travel window.',
            ];
        }

        $startDate = Carbon::parse($trip->start_date)->startOfDay();
        $endDate = Carbon::parse($trip->end_date ?? $trip->start_date)->startOfDay();

        if ($endDate->lt($startDate)) {
            return null;
        }

        if ($endDate->isPast()) {
            return [
                'available' => false,
                'reason' => 'past_trip',
                'message' => 'Weather is not shown for trips that have already ended.',
            ];
        }

        $today = today()->startOfDay();
        $daysUntilStart = (int) $today->diffInDays($startDate, false);
        $useForecast = $daysUntilStart <= self::FORECAST_HORIZON_DAYS;

        $cacheKey = sprintf(
            'trip_weather:%s:%s:%s:%s:%.4f:%.4f',
            $useForecast ? 'forecast' : 'typical',
            $startDate->toDateString(),
            $endDate->toDateString(),
            $today->toDateString(),
            $destination['lat'],
            $destination['lng'],
        );

        $ttl = $useForecast ? now()->addHour() : now()->addDay();

        return Cache::remember($cacheKey, $ttl, function () use (
            $destination,
            $startDate,
            $endDate,
            $today,
            $daysUntilStart,
            $useForecast,
        ): ?array {
            if ($useForecast) {
                return $this->buildForecastPayload(
                    $destination,
                    $startDate,
                    $endDate,
                    $today,
                    $daysUntilStart,
                );
            }

            return $this->buildTypicalPayload(
                $destination,
                $startDate,
                $endDate,
            );
        });
    }

    /**
     * @param  array{label: string|null, lat: float|null, lng: float|null, place_id: string|null, country_code: string|null}  $destination
     * @return array<string, mixed>|null
     */
    private function buildForecastPayload(
        array $destination,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        CarbonInterface $today,
        int $daysUntilStart,
    ): ?array {
        $forecastStart = $daysUntilStart < 0 ? $today : $startDate;
        $forecastEnd = $endDate->copy();

        $maxForecastEnd = $today->copy()->addDays(self::FORECAST_HORIZON_DAYS - 1);

        if ($forecastEnd->gt($maxForecastEnd)) {
            $forecastEnd = $maxForecastEnd;
        }

        if ($forecastEnd->lt($forecastStart)) {
            return $this->buildTypicalPayload($destination, $startDate, $endDate);
        }

        $response = $this->client->forecast(
            $destination['lat'],
            $destination['lng'],
            $forecastStart->toDateString(),
            $forecastEnd->toDateString(),
        );

        if (! $response->successful()) {
            Log::warning('Open-Meteo forecast request failed.', [
                'status' => $response->status(),
            ]);

            return [
                'available' => false,
                'reason' => 'fetch_failed',
                'message' => 'Weather forecast could not be loaded right now. Try again in a few minutes.',
                'location_label' => $destination['label'],
            ];
        }

        $days = $this->mapDailyRows($response->json('daily'));

        if ($days === []) {
            return [
                'available' => false,
                'reason' => 'fetch_failed',
                'message' => 'No forecast data returned for these dates yet.',
                'location_label' => $destination['label'],
            ];
        }

        $summary = $this->summarizeDays($days);

        return [
            'available' => true,
            'mode' => 'forecast',
            'mode_label' => 'Forecast',
            'location_label' => $destination['label'],
            'summary' => $summary,
            'days' => $days,
            'disclaimer' => $endDate->gt($maxForecastEnd)
                ? 'Daily forecast covers up to 16 days ahead. Typical seasonal conditions apply for later dates.'
                : 'Live forecast from Open-Meteo for your trip dates.',
            'source' => 'open_meteo',
        ];
    }

    /**
     * @param  array{label: string|null, lat: float|null, lng: float|null, place_id: string|null, country_code: string|null}  $destination
     * @return array<string, mixed>|null
     */
    private function buildTypicalPayload(
        array $destination,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): ?array {
        $maxTemps = [];
        $minTemps = [];
        $precipitationTotals = [];
        $rainyDays = 0;
        $totalDays = 0;

        for ($yearsAgo = 1; $yearsAgo <= self::TYPICAL_YEARS; $yearsAgo++) {
            $year = now()->year - $yearsAgo;
            $periodStart = $this->dateForYear($year, $startDate);
            $periodEnd = $this->dateForYear($year, $endDate);

            if ($periodStart === null || $periodEnd === null || $periodEnd->lt($periodStart)) {
                continue;
            }

            $response = $this->client->archive(
                $destination['lat'],
                $destination['lng'],
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            );

            if (! $response->successful()) {
                continue;
            }

            $days = $this->mapDailyRows($response->json('daily'));

            foreach ($days as $day) {
                $maxTemps[] = $day['temperature_max'];
                $minTemps[] = $day['temperature_min'];
                $precipitationTotals[] = $day['precipitation_mm'];
                $totalDays++;

                if ($day['precipitation_mm'] >= 1) {
                    $rainyDays++;
                }
            }
        }

        if ($totalDays === 0) {
            Log::warning('Open-Meteo typical weather could not be calculated.');

            return [
                'available' => false,
                'reason' => 'fetch_failed',
                'message' => 'Typical seasonal weather could not be loaded right now.',
                'location_label' => $destination['label'],
            ];
        }

        $avgMin = (int) round(array_sum($minTemps) / count($minTemps));
        $avgMax = (int) round(array_sum($maxTemps) / count($maxTemps));
        $avgDailyPrecip = round(array_sum($precipitationTotals) / count($precipitationTotals), 1);
        $rainyDayPercent = (int) round(($rainyDays / $totalDays) * 100);

        $periodLabel = $startDate->equalTo($endDate)
            ? $startDate->format('j M')
            : $startDate->format('j M').' – '.$endDate->format('j M');

        return [
            'available' => true,
            'mode' => 'typical',
            'mode_label' => 'Typical for this season',
            'location_label' => $destination['label'],
            'period_label' => $periodLabel,
            'summary' => sprintf(
                'Usually %d–%d°C with ~%s mm rain/day (%d%% days with rain).',
                $avgMin,
                $avgMax,
                number_format($avgDailyPrecip, 1),
                $rainyDayPercent,
            ),
            'temperature_min' => $avgMin,
            'temperature_max' => $avgMax,
            'avg_daily_precipitation_mm' => $avgDailyPrecip,
            'rainy_day_percent' => $rainyDayPercent,
            'sample_years' => self::TYPICAL_YEARS,
            'disclaimer' => sprintf(
                'Based on %d-year historical averages for %s — not a day-by-day forecast. Check again about two weeks before you travel for a live forecast.',
                self::TYPICAL_YEARS,
                $periodLabel,
            ),
            'source' => 'open_meteo',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $daily
     * @return list<array{
     *     date: string,
     *     temperature_min: int,
     *     temperature_max: int,
     *     precipitation_mm: float,
     *     weather_code: int,
     *     weather_label: string,
     *     weather_kind: string,
     * }>
     */
    private function mapDailyRows(?array $daily): array
    {
        if ($daily === null) {
            return [];
        }

        $dates = $daily['time'] ?? [];
        $maxTemps = $daily['temperature_2m_max'] ?? [];
        $minTemps = $daily['temperature_2m_min'] ?? [];
        $precipitation = $daily['precipitation_sum'] ?? [];
        $weatherCodes = $daily['weathercode'] ?? [];

        $days = [];

        foreach ($dates as $index => $date) {
            if (! is_string($date)) {
                continue;
            }

            $code = (int) ($weatherCodes[$index] ?? 0);
            $description = WeatherCode::describe($code);

            $days[] = [
                'date' => $date,
                'temperature_min' => (int) round((float) ($minTemps[$index] ?? 0)),
                'temperature_max' => (int) round((float) ($maxTemps[$index] ?? 0)),
                'precipitation_mm' => round((float) ($precipitation[$index] ?? 0), 1),
                'weather_code' => $code,
                'weather_label' => $description['label'],
                'weather_kind' => $description['kind'],
            ];
        }

        return $days;
    }

    /**
     * @param  list<array{
     *     date: string,
     *     temperature_min: int,
     *     temperature_max: int,
     *     precipitation_mm: float,
     *     weather_code: int,
     *     weather_label: string,
     *     weather_kind: string,
     * }>  $days
     */
    private function summarizeDays(array $days): string
    {
        $minTemps = array_column($days, 'temperature_min');
        $maxTemps = array_column($days, 'temperature_max');
        $rainyDays = count(array_filter($days, fn (array $day): bool => $day['precipitation_mm'] >= 1));

        return sprintf(
            '%d–%d°C across %d days (%d rainy days in forecast window).',
            min($minTemps),
            max($maxTemps),
            count($days),
            $rainyDays,
        );
    }

    private function dateForYear(int $year, CarbonInterface $reference): ?Carbon
    {
        if ($reference->month === 2 && $reference->day === 29 && ! Carbon::create($year, 1, 1)->isLeapYear()) {
            return Carbon::create($year, 2, 28)->startOfDay();
        }

        return Carbon::create($year, $reference->month, $reference->day)->startOfDay();
    }
}
