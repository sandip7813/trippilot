<?php

namespace App\Services\RoadTrips;

use App\Contracts\Maps\PlacesService;
use App\Data\Maps\PlaceResult;
use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Models\Trip;

class RoadTripAmenitiesService
{
    private const HOTEL_SEARCH_RADIUS_METERS = 8000;

    private const HOTEL_SEARCH_LIMIT = 10;

    private const DEFAULT_SEARCH_RADIUS_METERS = 2000;

    private const DEFAULT_SEARCH_LIMIT = 5;

    /** @var array<string, string> */
    public const LAYER_LABELS = [
        'fuel' => 'Fuel stations',
        'ev' => 'EV charging',
        'hotels' => 'Hotels',
        'food' => 'Food',
        'toilets' => 'Restrooms',
        'supermarkets' => 'Supermarkets & stores',
        'atm' => 'ATMs',
        'parking' => 'Parking',
        'pharmacy' => 'Pharmacies',
        'hospitals' => 'Hospitals & clinics',
        'mechanics' => 'Mechanics & garages',
        'tyres' => 'Tyre shops',
        'rest_areas' => 'Rest areas',
        'emergency' => 'Police & emergency',
        'viewpoints' => 'Viewpoints',
        'bike' => 'Bike shops & repair',
    ];

    /** @var array<string, string> */
    public const LAYER_CATEGORIES = [
        'fuel' => 'service.vehicle.fuel',
        'ev' => 'service.vehicle.charging_station',
        'hotels' => 'accommodation.hotel,accommodation.motel,accommodation.guest_house,accommodation.hostel,accommodation.apartment',
        'food' => 'catering.restaurant,catering.cafe,catering.fast_food',
        'toilets' => 'amenity.toilet',
        'supermarkets' => 'commercial.supermarket,commercial.convenience',
        'atm' => 'service.financial.atm',
        'parking' => 'parking',
        'pharmacy' => 'healthcare.pharmacy',
        'hospitals' => 'healthcare.hospital,healthcare.clinic_or_praxis',
        'mechanics' => 'service.vehicle.repair',
        'tyres' => 'commercial.vehicle',
        'rest_areas' => 'leisure.picnic,highway.motorway.junction',
        'emergency' => 'emergency,service.police,service.fire_station',
        'viewpoints' => 'tourism.attraction.viewpoint,tourism.attraction',
        'bike' => 'service.vehicle.bicycle,rental.bicycle',
    ];

    public function __construct(private PlacesService $placesService) {}

    /**
     * @return list<string>
     */
    public function layersForTrip(Trip $trip): array
    {
        $profile = Trip::coerceStructuredArray($trip->getAttribute('road_profile')) ?? [];
        $vehicle = VehicleClass::tryFrom((string) ($profile['vehicle_class'] ?? 'car'))
            ?? VehicleClass::Car;
        $fuel = FuelType::tryFrom((string) ($profile['fuel_type'] ?? 'petrol')) ?? FuelType::Petrol;

        if ($vehicle === VehicleClass::Bicycle) {
            return [
                'food',
                'hotels',
                'toilets',
                'supermarkets',
                'atm',
                'pharmacy',
                'hospitals',
                'rest_areas',
                'emergency',
                'viewpoints',
                'bike',
            ];
        }

        $layers = [
            'hotels',
            'food',
            'toilets',
            'supermarkets',
            'atm',
            'pharmacy',
            'hospitals',
            'rest_areas',
            'emergency',
            'viewpoints',
            'parking',
            'mechanics',
            'tyres',
        ];

        if ($fuel !== FuelType::None && $fuel !== FuelType::Ev) {
            $layers[] = 'fuel';
        }

        if (in_array($fuel, [FuelType::Ev, FuelType::Hybrid, FuelType::Cng], true)) {
            $layers[] = 'ev';
        }

        $order = array_keys(self::LAYER_CATEGORIES);

        return array_values(array_filter(
            $order,
            fn (string $layer): bool => in_array($layer, $layers, true),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchForTrip(Trip $trip, string $layer): array
    {
        $categories = $this->categoriesForLayer($layer, $trip);
        $samplePoints = $this->sampleRoutePoints($trip, $layer);

        if ($categories === [] || $samplePoints === []) {
            return [];
        }

        $places = [];

        foreach ($samplePoints as $point) {
            $searchResults = $layer === 'hotels'
                ? $this->placesService->searchNearPoint(
                    $point['lat'],
                    $point['lng'],
                    $categories,
                    self::HOTEL_SEARCH_LIMIT,
                    self::HOTEL_SEARCH_RADIUS_METERS,
                )
                : $this->searchLayerCategories(
                    $point['lat'],
                    $point['lng'],
                    $categories,
                    self::DEFAULT_SEARCH_LIMIT,
                    self::DEFAULT_SEARCH_RADIUS_METERS,
                );

            foreach ($searchResults as $place) {
                $record = $place->toArray();

                if (isset($point['zone'])) {
                    $record['route_zone'] = $point['zone'];
                }

                $places[$this->placeKey($place)] = $record;
            }
        }

        $places = array_values($places);

        if ($layer === 'hotels') {
            $places = $this->excludeHotelsNearOrigin($trip, $places);
        }

        return $places;
    }

    /**
     * @return list<string>
     */
    private function categoriesForLayer(string $layer, Trip $trip): array
    {
        if ($layer === 'fuel') {
            $roadProfile = Trip::coerceStructuredArray($trip->getAttribute('road_profile')) ?? [];
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
     * @return list<array{lat: float, lng: float, zone?: string}>
     */
    private function sampleRoutePoints(Trip $trip, string $layer): array
    {
        $route = $trip->routeData() ?? [];
        /** @var list<array{0: float, 1: float}> $polyline */
        $polyline = is_array($route['polyline'] ?? null) ? $route['polyline'] : [];

        if ($polyline === []) {
            return [];
        }

        $count = count($polyline);
        $lastIndex = $count - 1;

        if ($layer === 'hotels') {
            return $this->sampleHotelRoutePoints($polyline);
        }

        $fractions = [0.0, 0.25, 0.5, 0.75, 1.0];

        $indexes = array_values(array_unique(array_map(
            fn (float $fraction): int => min($lastIndex, max(0, (int) floor($fraction * $lastIndex))),
            $fractions,
        )));

        return array_map(fn (int $index): array => [
            'lat' => (float) $polyline[$index][0],
            'lng' => (float) $polyline[$index][1],
        ], $indexes);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $polyline
     * @return list<array{lat: float, lng: float, zone: string}>
     */
    private function sampleHotelRoutePoints(array $polyline): array
    {
        $lastIndex = count($polyline) - 1;

        if ($lastIndex === 0) {
            return [[
                'lat' => (float) $polyline[0][0],
                'lng' => (float) $polyline[0][1],
                'zone' => 'destination',
            ]];
        }

        /** @var list<float> $cumulativeKm */
        $cumulativeKm = [0.0];

        for ($index = 1; $index <= $lastIndex; $index++) {
            $cumulativeKm[$index] = $cumulativeKm[$index - 1] + $this->distanceKm(
                (float) $polyline[$index - 1][0],
                (float) $polyline[$index - 1][1],
                (float) $polyline[$index][0],
                (float) $polyline[$index][1],
            );
        }

        $totalKm = $cumulativeKm[$lastIndex];
        $skipFromOriginKm = min(25.0, max(10.0, $totalKm * 0.1));
        $destinationZoneStartKm = $totalKm * 0.85;
        $intervalKm = max(30.0, ($destinationZoneStartKm - $skipFromOriginKm) / 4);

        $targetDistances = [];

        for ($km = $skipFromOriginKm; $km < $destinationZoneStartKm; $km += $intervalKm) {
            $targetDistances[] = $km;
        }

        $targetDistances[] = max($destinationZoneStartKm, $totalKm * 0.92);
        $targetDistances[] = $totalKm;

        $targetDistances = array_values(array_unique(array_map(
            fn (float $km): float => round(min($totalKm, max(0.0, $km)), 2),
            $targetDistances,
        )));

        return array_map(function (float $targetKm) use ($polyline, $cumulativeKm, $destinationZoneStartKm): array {
            $index = $this->polylineIndexAtDistance($cumulativeKm, $targetKm);

            return [
                'lat' => (float) $polyline[$index][0],
                'lng' => (float) $polyline[$index][1],
                'zone' => $targetKm >= $destinationZoneStartKm ? 'destination' : 'en_route',
            ];
        }, $targetDistances);
    }

    /**
     * @param  list<float>  $cumulativeKm
     */
    private function polylineIndexAtDistance(array $cumulativeKm, float $targetKm): int
    {
        foreach ($cumulativeKm as $index => $km) {
            if ($km >= $targetKm) {
                return (int) $index;
            }
        }

        return max(0, count($cumulativeKm) - 1);
    }

    /**
     * @param  list<string>  $categories
     * @return list<PlaceResult>
     */
    private function searchLayerCategories(
        float $latitude,
        float $longitude,
        array $categories,
        int $limit,
        int $radiusMeters,
    ): array {
        if ($categories === []) {
            return [];
        }

        $places = [];

        foreach ($this->placesService->searchNearby(
            $latitude,
            $longitude,
            implode(',', $categories),
            $limit,
            $radiusMeters,
        ) as $place) {
            $places[$this->placeKey($place)] = $place;
        }

        return array_values($places);
    }

    /**
     * @param  list<array<string, mixed>>  $places
     * @return list<array<string, mixed>>
     */
    private function excludeHotelsNearOrigin(Trip $trip, array $places): array
    {
        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));

        if ($origin === null || $origin['lat'] === null || $origin['lng'] === null) {
            return $places;
        }

        $routeDistanceKm = (float) ($trip->routeData()['distance_km'] ?? 0);
        $minimumDistanceKm = min(30.0, max(8.0, $routeDistanceKm * 0.15));

        return array_values(array_filter(
            $places,
            function (array $place) use ($origin, $minimumDistanceKm): bool {
                if (($place['route_zone'] ?? null) === 'destination') {
                    return true;
                }

                return $this->distanceKm(
                    (float) $origin['lat'],
                    (float) $origin['lng'],
                    (float) $place['lat'],
                    (float) $place['lng'],
                ) >= $minimumDistanceKm;
            },
        ));
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function placeKey(PlaceResult $place): string
    {
        return $place->placeId ?? "{$place->latitude}:{$place->longitude}:{$place->name}";
    }
}
