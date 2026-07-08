<?php

namespace App\Support\RoadTrip;

use App\Models\Trip;

class RoadTripWaypointBuilder
{
    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function build(Trip $trip): array
    {
        $points = [];

        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));

        if ($origin !== null && $origin['lat'] !== null && $origin['lng'] !== null) {
            $points[] = ['lat' => $origin['lat'], 'lng' => $origin['lng']];
        }

        /** @var list<array<string, mixed>> $stops */
        $stops = is_array($trip->stops) ? $trip->stops : [];

        foreach ($stops as $stop) {
            $lat = isset($stop['lat']) ? (float) $stop['lat'] : null;
            $lng = isset($stop['lng']) ? (float) $stop['lng'] : null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $points[] = ['lat' => $lat, 'lng' => $lng];
        }

        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));

        if ($destination !== null && $destination['lat'] !== null && $destination['lng'] !== null) {
            $last = $points !== [] ? $points[array_key_last($points)] : null;

            if ($last === null || $last['lat'] !== $destination['lat'] || $last['lng'] !== $destination['lng']) {
                $points[] = ['lat' => $destination['lat'], 'lng' => $destination['lng']];
            }
        }

        return $points;
    }
}
