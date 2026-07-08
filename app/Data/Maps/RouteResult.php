<?php

namespace App\Data\Maps;

readonly class RouteResult
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $polyline
     * @param  array<int, mixed>  $legs
     */
    public function __construct(
        public float $distanceKm,
        public int $durationSeconds,
        public bool $hasTolls,
        public array $polyline,
        public array $legs = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'distance_km' => (float) $this->distanceKm,
            'duration_seconds' => $this->durationSeconds,
            'has_tolls' => $this->hasTolls,
            'polyline' => $this->polyline,
            'legs' => $this->legs,
            'computed_at' => now()->toIso8601String(),
        ];
    }
}
