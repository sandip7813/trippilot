<?php

namespace App\Services\Hotels\Geoapify;

use App\Contracts\Hotels\HotelsService;
use App\Data\Hotels\HotelResult;
use App\Services\Maps\Geoapify\GeoapifyClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeoapifyHotelsService implements HotelsService
{
    private const string CATEGORY = 'accommodation.hotel';

    /**
     * @var array<string, string>
     */
    private const array FACILITY_LABELS = [
        'internet_access' => 'Internet access',
        'air_conditioning' => 'Air conditioning',
        'swimming_pool' => 'Swimming pool',
        'wheelchair' => 'Wheelchair access',
        'smoking' => 'Smoking allowed',
    ];

    public function __construct(private GeoapifyClient $client) {}

    /**
     * @return array{hotels: list<HotelResult>, has_more: bool}|null
     */
    public function searchNearby(
        float $latitude,
        float $longitude,
        int $limit,
        int $offset = 0,
        int $radiusMeters = 10000,
    ): ?array {
        if (! filled(config('integrations.maps.drivers.geoapify.api_key'))) {
            return null;
        }

        try {
            $response = $this->client->getV2('places', [
                'categories' => self::CATEGORY,
                'filter' => "circle:{$longitude},{$latitude},{$radiusMeters}",
                'bias' => "proximity:{$longitude},{$latitude}",
                'conditions' => 'named',
                'limit' => $limit + 1,
                'offset' => $offset,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('Geoapify hotel search could not connect.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Geoapify hotel search failed.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        /** @var list<array<string, mixed>> $features */
        $features = $response->json('features') ?? [];

        return [
            'hotels' => collect($features)
                ->take($limit)
                ->map(fn (array $feature): ?HotelResult => $this->mapFeature($feature))
                ->filter()
                ->values()
                ->all(),
            'has_more' => count($features) > $limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     */
    private function mapFeature(array $feature): ?HotelResult
    {
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $name = $this->filledString($properties['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $accommodation = is_array($properties['accommodation'] ?? null) ? $properties['accommodation'] : [];
        $contact = is_array($properties['contact'] ?? null) ? $properties['contact'] : [];

        return new HotelResult(
            name: $name,
            address: $this->filledString($properties['address_line2'] ?? null),
            latitude: isset($properties['lat']) ? (float) $properties['lat'] : null,
            longitude: isset($properties['lon']) ? (float) $properties['lon'] : null,
            distanceMeters: isset($properties['distance']) ? (int) $properties['distance'] : null,
            placeId: $this->filledString($properties['place_id'] ?? null),
            phone: $this->filledString($contact['phone'] ?? null),
            email: $this->filledString($contact['email'] ?? null),
            website: $this->webUrl($properties['website'] ?? ($contact['website'] ?? null)),
            stars: isset($accommodation['stars']) ? (int) $accommodation['stars'] : null,
            rooms: isset($accommodation['rooms']) ? (int) $accommodation['rooms'] : null,
            facilities: $this->facilityLabels($properties['facilities'] ?? []),
            openingHours: $this->filledString($properties['opening_hours'] ?? null),
        );
    }

    private function filledString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function webUrl(mixed $value): ?string
    {
        $url = $this->filledString($value);

        return $url !== null && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /**
     * @return list<string>
     */
    private function facilityLabels(mixed $facilities): array
    {
        if (! is_array($facilities)) {
            return [];
        }

        return collect($facilities)
            ->filter(fn (mixed $enabled): bool => $enabled === true)
            ->keys()
            ->map(fn (string $facility): string => self::FACILITY_LABELS[$facility] ?? Str::headline($facility))
            ->values()
            ->all();
    }
}
