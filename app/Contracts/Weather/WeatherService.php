<?php

namespace App\Contracts\Weather;

use App\Data\Weather\WeatherForecast;

interface WeatherService
{
    public function getForecast(float $latitude, float $longitude): WeatherForecast;
}
