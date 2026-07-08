<?php

namespace App\Contracts\Maps;

use App\Data\Maps\RouteResult;

interface RoutingService
{
    /**
     * @param  array<int, array{lat: float, lng: float}>  $waypoints
     * @param  list<string>  $avoid
     */
    public function getRoute(array $waypoints, string $mode = 'drive', array $avoid = []): RouteResult;
}
