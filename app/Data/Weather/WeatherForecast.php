<?php

namespace App\Data\Weather;

readonly class WeatherForecast
{
    /**
     * @param  array<int, array{date: string, temp_c: float, description: string}>  $daily
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public array $daily,
    ) {}
}
