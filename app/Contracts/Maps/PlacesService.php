<?php

namespace App\Contracts\Maps;

use App\Data\Maps\PlaceResult;

interface PlacesService
{
    /**
     * @return array<int, PlaceResult>
     */
    public function searchNearby(
        float $latitude,
        float $longitude,
        string $category,
        int $limit = 10,
        int $radiusMeters = 2000,
    ): array;

    /**
     * @param  list<string>  $categories
     * @return array<int, PlaceResult>
     */
    public function searchNearPoint(
        float $latitude,
        float $longitude,
        array $categories,
        int $limit = 15,
        int $radiusMeters = 2000,
    ): array;
}
