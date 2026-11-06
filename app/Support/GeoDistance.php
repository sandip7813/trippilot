<?php

namespace App\Support;

class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle (haversine) distance between two coordinates.
     */
    public static function kilometers(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
