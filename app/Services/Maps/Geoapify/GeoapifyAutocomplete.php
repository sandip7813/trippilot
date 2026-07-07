<?php

namespace App\Services\Maps\Geoapify;

use Illuminate\Support\Facades\Log;

class GeoapifyAutocomplete
{
    public function __construct(private GeoapifyClient $client) {}

    public function isConfigured(): bool
    {
        return filled(config('integrations.maps.drivers.geoapify.api_key'));
    }

    /**
     * @return list<array{
     *     label: string,
     *     lat: float,
     *     lng: float,
     *     place_id: string|null,
     *     country_code: string|null,
     * }>
     */
    public function search(string $query, int $limit = 6): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $trimmed = trim($query);

        if (mb_strlen($trimmed) < 2) {
            return [];
        }

        $queryParams = [
            'text' => $trimmed,
            'limit' => max(1, min($limit, 10)),
            'format' => 'json',
            'lang' => 'en',
            'type' => 'city',
        ];

        $defaultCountry = strtolower((string) config('integrations.maps.default_country', 'in'));

        if ($defaultCountry !== '') {
            $queryParams['bias'] = "countrycode:{$defaultCountry}";
        }

        $response = $this->client->get('/geocode/autocomplete', $queryParams);

        if (! $response->successful()) {
            Log::warning('Geoapify autocomplete request failed.', [
                'status' => $response->status(),
                'query' => $trimmed,
            ]);

            return [];
        }

        return $this->mapResults($response->json('results', []));
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array{
     *     label: string,
     *     lat: float,
     *     lng: float,
     *     place_id: string|null,
     *     country_code: string|null,
     * }>
     */
    private function mapResults(array $results): array
    {
        $suggestions = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $label = $result['formatted'] ?? $result['address_line1'] ?? null;

            if (! is_string($label) || $label === '') {
                continue;
            }

            $lat = $result['lat'] ?? null;
            $lng = $result['lon'] ?? $result['lng'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $countryCode = $result['country_code'] ?? null;

            $suggestions[] = [
                'label' => $label,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'place_id' => isset($result['place_id']) ? (string) $result['place_id'] : null,
                'country_code' => is_string($countryCode) && $countryCode !== ''
                    ? strtolower($countryCode)
                    : null,
            ];
        }

        return $suggestions;
    }
}
