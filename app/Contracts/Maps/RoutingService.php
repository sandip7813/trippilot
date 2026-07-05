<?php

namespace App\Contracts\Maps;

use App\Data\Maps\RouteResult;

interface RoutingService
{
    /**
     * @param  array<int, array{lat: float, lng: float}>  $waypoints
     */
    public function getRoute(array $waypoints, string $mode = 'drive'): RouteResult;
}
