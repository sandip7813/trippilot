<?php

namespace App\Data\TripCovers;

readonly class TripCoverCandidate
{
    /**
     * @param  array<string, string|null>|null  $attribution
     */
    public function __construct(
        public string $ref,
        public string $imageUrl,
        public ?array $attribution = null,
    ) {}
}
