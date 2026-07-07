<?php

namespace App\Contracts\TripCovers;

interface TripCoverGenerator
{
    /**
     * Generate a destination cover image at the requested dimensions.
     *
     * @param  array<string, mixed>  $destination  Normalized trip destination (label, lat, lng, country_code, …)
     * @return non-empty-string|null Raw image bytes
     */
    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string;
}
