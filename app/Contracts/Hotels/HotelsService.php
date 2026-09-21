<?php

namespace App\Contracts\Hotels;

use App\Data\Hotels\HotelResult;

interface HotelsService
{
    /**
     * Returns one page of hotels nearest to the point, or null when the
     * provider could not be reached.
     *
     * @return array{hotels: list<HotelResult>, has_more: bool}|null
     */
    public function searchNearby(
        float $latitude,
        float $longitude,
        int $limit,
        int $offset = 0,
        int $radiusMeters = 10000,
    ): ?array;
}
