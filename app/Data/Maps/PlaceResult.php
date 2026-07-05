<?php

namespace App\Data\Maps;

readonly class PlaceResult
{
    public function __construct(
        public string $name,
        public string $category,
        public float $latitude,
        public float $longitude,
        public ?string $address = null,
    ) {}
}
