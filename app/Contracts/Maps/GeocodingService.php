<?php

namespace App\Contracts\Maps;

use App\Data\Maps\GeocodingResult;

interface GeocodingService
{
    public function geocode(string $address): GeocodingResult;

    public function reverseGeocode(float $latitude, float $longitude): GeocodingResult;
}
