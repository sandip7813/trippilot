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
}
