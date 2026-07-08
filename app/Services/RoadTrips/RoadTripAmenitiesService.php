<?php

namespace App\Services\RoadTrips;

use App\Contracts\Maps\PlacesService;
use App\Data\Maps\PlaceResult;
use App\Enums\FuelType;
use App\Models\Trip;

class RoadTripAmenitiesService
{
    /** @var array<string, string> */
    public const LAYER_CATEGORIES = [
        'fuel' => 'service.vehicle.fuel',
        'ev' => 'service.vehicle.charging_station',
        'hotels' => 'accommodation.hotel,accommodation.motel,accommodation.guest_house',
        'food' => 'catering.restaurant,catering.cafe,catering.fast_food',
        'parking' => 'parking',
        'pharmacy' => 'healthcare.pharmacy',
        'viewpoints' => 'tourism.viewpoint,tourism.attraction',
    ];

    public function __construct(private PlacesService $placesService) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchForTrip(Trip $trip, string $layer): array
    {
        $categories = $this->categoriesForLayer($layer, $trip);
        $samplePoints = $this->sampleRoutePoints($trip);

        if ($categories === [] || $samplePoints === []) {
            return [];
        }

        $places = [];

        foreach ($samplePoints as $point) {
            foreach ($categories as $category) {
                foreach ($this->placesService->searchNearby($point['lat'], $point['lng'], $category, 5) as $place) {
                    $places[$this->placeKey($place)] = $place->toArray();
                }
            }
        }

        return array_values($places);
    }

    /**
     * @return list<string>
     */
    private function categoriesForLayer(string $layer, Trip $trip): array
    {
        if ($layer === 'fuel') {
            $roadProfile = is_array($trip->road_profile) ? $trip->road_profile : [];
            $fuelType = FuelType::tryFrom((string) ($roadProfile['fuel_type'] ?? 'petrol')) ?? FuelType::Petrol;

            return $fuelType->defaultAmenityCategories();
        }

        $mapping = self::LAYER_CATEGORIES[$layer] ?? null;

        if ($mapping === null) {
            return [];
        }

        return explode(',', $mapping);
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    private function sampleRoutePoints(Trip $trip): array
    {
        $route = is_array($trip->route) ? $trip->route : [];
        /** @var list<array{0: float, 1: float}> $polyline */
        $polyline = is_array($route['polyline'] ?? null) ? $route['polyline'] : [];

        if ($polyline === []) {
            return [];
        }

        $count = count($polyline);
        $indexes = [0, (int) floor($count * 0.25), (int) floor($count * 0.5), (int) floor($count * 0.75), $count - 1];
        $indexes = array_values(array_unique($indexes));

        return array_map(
            fn (int $index): array => [
                'lat' => (float) $polyline[$index][0],
                'lng' => (float) $polyline[$index][1],
            ],
            $indexes,
        );
    }

    private function placeKey(PlaceResult $place): string
    {
        return $place->placeId ?? "{$place->latitude}:{$place->longitude}:{$place->name}";
    }
}
