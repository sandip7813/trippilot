<?php

namespace App\Contracts\Ai;

use App\Data\Ai\GeneratedItinerary;

interface TripGenerator
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function generate(string $prompt, array $context = []): GeneratedItinerary;
}
