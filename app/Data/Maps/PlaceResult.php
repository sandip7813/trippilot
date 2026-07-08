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
        public ?string $placeId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'address' => $this->address,
            'place_id' => $this->placeId,
        ];
    }
}
