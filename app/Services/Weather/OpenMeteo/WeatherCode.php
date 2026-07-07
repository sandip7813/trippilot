<?php

namespace App\Services\Weather\OpenMeteo;

class WeatherCode
{
    /**
     * @return array{label: string, kind: string}
     */
    public static function describe(int $code): array
    {
        return match (true) {
            $code === 0 => ['label' => 'Clear sky', 'kind' => 'clear'],
            in_array($code, [1, 2, 3], true) => ['label' => 'Partly cloudy', 'kind' => 'cloudy'],
            in_array($code, [45, 48], true) => ['label' => 'Foggy', 'kind' => 'fog'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['label' => 'Drizzle', 'kind' => 'rain'],
            in_array($code, [61, 63, 65, 66, 67], true) => ['label' => 'Rain', 'kind' => 'rain'],
            in_array($code, [71, 73, 75, 77], true) => ['label' => 'Snow', 'kind' => 'snow'],
            in_array($code, [80, 81, 82], true) => ['label' => 'Rain showers', 'kind' => 'rain'],
            in_array($code, [85, 86], true) => ['label' => 'Snow showers', 'kind' => 'snow'],
            in_array($code, [95, 96, 99], true) => ['label' => 'Thunderstorm', 'kind' => 'storm'],
            default => ['label' => 'Mixed conditions', 'kind' => 'cloudy'],
        };
    }
}
