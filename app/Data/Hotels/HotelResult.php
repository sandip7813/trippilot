<?php

namespace App\Data\Hotels;

readonly class HotelResult
{
    /**
     * @param  list<string>  $facilities
     */
    public function __construct(
        public string $name,
        public ?string $address = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $distanceMeters = null,
        public ?string $placeId = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public ?int $stars = null,
        public ?int $rooms = null,
        public array $facilities = [],
        public ?string $openingHours = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'distance_meters' => $this->distanceMeters,
            'place_id' => $this->placeId,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'stars' => $this->stars,
            'rooms' => $this->rooms,
            'facilities' => $this->facilities,
            'opening_hours' => $this->openingHours,
        ];
    }
}
