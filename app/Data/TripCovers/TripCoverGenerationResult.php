<?php

namespace App\Data\TripCovers;

use App\Enums\TripCoverSource;

readonly class TripCoverGenerationResult
{
    /**
     * @param  non-empty-string  $bytes
     * @param  list<string>  $triedRefs
     * @param  array<string, string|null>|null  $attribution
     */
    public function __construct(
        public string $bytes,
        public TripCoverSource $source,
        public string $ref,
        public int $sourceIndex,
        public ?array $attribution,
        public array $triedRefs,
    ) {}
}
