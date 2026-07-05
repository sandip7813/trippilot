<?php

namespace App\Contracts\Maps;

use App\Data\Maps\PlaceResult;

interface PlacesService
{
    /**
     * @return array<int, PlaceResult>
     */
    public function searchNearby(float $latitude, float $longitude, string $category, int $limit = 10): array;
}
