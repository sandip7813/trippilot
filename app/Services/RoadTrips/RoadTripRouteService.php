<?php

namespace App\Services\RoadTrips;

use App\Contracts\Maps\RoutingService;
use App\Data\Maps\RouteResult;
use App\Enums\VehicleClass;
use App\Models\Trip;
use App\Support\RoadTrip\RoadTripWaypointBuilder;

class RoadTripRouteService
{
    public function __construct(
        private RoutingService $routingService,
        private RoadTripWaypointBuilder $waypointBuilder,
    ) {}

    public function computeAndStore(Trip $trip): RouteResult
    {
        $roadProfile = Trip::coerceStructuredArray($trip->getAttribute('road_profile')) ?? [];
        $vehicleClass = VehicleClass::tryFrom((string) ($roadProfile['vehicle_class'] ?? 'car')) ?? VehicleClass::Car;

        $waypoints = $this->waypointBuilder->build($trip);
        $avoid = $this->avoidFlags($roadProfile);

        $route = $this->routingService->getRoute(
            $waypoints,
            $vehicleClass->routingMode(),
            $avoid,
        );

        $trip->update([
            'route' => $route->toArray(),
        ]);

        return $route;
    }

    /**
     * @param  array<string, mixed>  $roadProfile
     * @return list<string>
     */
    private function avoidFlags(array $roadProfile): array
    {
        $avoid = [];

        if (filter_var($roadProfile['avoid_tolls'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $avoid[] = 'tolls';
        }

        if (filter_var($roadProfile['avoid_highways'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $avoid[] = 'highways';
        }

        return $avoid;
    }
}
