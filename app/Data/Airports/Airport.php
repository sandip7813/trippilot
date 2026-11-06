<?php

namespace App\Data\Airports;

readonly class Airport
{
    public function __construct(
        public string $iataCode,
        public string $name,
        public ?string $municipality,
        public string $countryCode,
        public string $type,
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * OurAirports' "large_airport" type: a major hub, usually with
     * international flights.
     */
    public function isMajor(): bool
    {
        return $this->type === 'large_airport';
    }

    /**
     * @return array{iata_code: string, name: string, municipality: string|null, country_code: string, is_major: bool, lat: float, lng: float}
     */
    public function toArray(): array
    {
        return [
            'iata_code' => $this->iataCode,
            'name' => $this->name,
            'municipality' => $this->municipality,
            'country_code' => $this->countryCode,
            'is_major' => $this->isMajor(),
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }
}
