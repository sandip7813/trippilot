<?php

namespace App\Services\Maps\Geoapify;

use App\Contracts\Maps\PlacesService;
use App\Data\Maps\PlaceResult;
use Illuminate\Support\Facades\Log;

class GeoapifyPlacesService implements PlacesService
{
    public function __construct(private GeoapifyClient $client) {}

    /**
     * @return array<int, PlaceResult>
     */
    public function searchNearby(
        float $latitude,
        float $longitude,
        string $category,
        int $limit = 10,
        int $radiusMeters = 2000,
    ): array {
        if (! filled(config('integrations.maps.drivers.geoapify.api_key'))) {
            return [];
        }

        $response = $this->client->getV2('places', [
            'categories' => $category,
            'filter' => "circle:{$longitude},{$latitude},{$radiusMeters}",
            'bias' => "proximity:{$longitude},{$latitude}",
            'limit' => min($limit, 20),
        ]);

        if ($response->failed()) {
            Log::warning('Geoapify places search failed.', [
                'status' => $response->status(),
                'category' => $category,
            ]);

            return [];
        }

        /** @var list<array<string, mixed>> $features */
        $features = $response->json('features') ?? [];

        return collect($features)
            ->map(fn (array $feature): ?PlaceResult => $this->mapFeature($feature, $category))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $categories
     * @return array<int, PlaceResult>
     */
    public function searchNearPoint(
        float $latitude,
        float $longitude,
        array $categories,
        int $limit = 15,
        int $radiusMeters = 2000,
    ): array {
        $results = [];

        foreach ($categories as $category) {
            foreach ($this->searchNearby($latitude, $longitude, $category, $limit, $radiusMeters) as $place) {
                $key = $place->placeId ?? "{$place->latitude},{$place->longitude},{$place->name}";

                if (! isset($results[$key])) {
                    $results[$key] = $place;
                }
            }
        }

        return array_values($results);
    }

    /**
     * @param  array<string, mixed>  $feature
     */
    private function mapFeature(array $feature, string $category): ?PlaceResult
    {
        $geometry = $feature['geometry'] ?? null;
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $name = (string) ($properties['name'] ?? $properties['address_line1'] ?? 'Unnamed place');

        if ($name === '') {
            return null;
        }

        return new PlaceResult(
            name: $name,
            category: $this->resolveCategory($properties['categories'] ?? null, $category),
            latitude: (float) $coordinates[1],
            longitude: (float) $coordinates[0],
            address: isset($properties['formatted']) ? (string) $properties['formatted'] : null,
            placeId: isset($properties['place_id']) ? (string) $properties['place_id'] : null,
        );
    }

    private function resolveCategory(mixed $categories, string $fallback): string
    {
        if (is_string($categories) && $categories !== '') {
            return $categories;
        }

        if (! is_array($categories)) {
            return $fallback;
        }

        $labels = [];

        foreach ($categories as $category) {
            if (is_string($category) && $category !== '') {
                $labels[] = $category;

                continue;
            }

            if (is_array($category) && is_string($category['name'] ?? null) && $category['name'] !== '') {
                $labels[] = $category['name'];
            }
        }

        return $labels !== [] ? implode(', ', array_values(array_unique($labels))) : $fallback;
    }
}
