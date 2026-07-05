<?php

namespace App\Data\Maps;

readonly class GeocodingResult
{
    public function __construct(
        public string $formattedAddress,
        public float $latitude,
        public float $longitude,
    ) {}
}
