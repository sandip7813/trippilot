<?php

namespace App\Data\Maps;

readonly class PlaceResult
{
    /**
     * @param  array<string, mixed>  $tags  Raw OpenStreetMap tags (operator, network, phone, website, ...).
     */
    public function __construct(
        public string $name,
        public string $category,
        public float $latitude,
        public float $longitude,
        public ?string $address = null,
        public ?string $placeId = null,
        public array $tags = [],
    ) {}

    /**
     * A non-empty string tag value, or null.
     */
    public function tag(string $key): ?string
    {
        $value = $this->tags[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

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
