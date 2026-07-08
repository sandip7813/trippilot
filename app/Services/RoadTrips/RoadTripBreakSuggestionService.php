<?php

namespace App\Services\RoadTrips;

use App\Enums\DrivingPace;
use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Exceptions\AiGenerationException;
use App\Models\Trip;
use App\Services\Ai\Gemini\GeminiClient;
use App\Support\GeminiResponseErrors;
use Illuminate\Support\Str;
use JsonException;

class RoadTripBreakSuggestionService
{
    public function __construct(
        private RoadTripAmenitiesService $amenitiesService,
        private GeminiClient $client,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function suggest(Trip $trip): array
    {
        $candidates = $this->gatherCandidates($trip);

        if ($candidates === []) {
            return $this->fallbackBreaks($trip);
        }

        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return $this->fallbackBreaks($trip, $candidates);
        }

        $response = $this->client->post(
            $this->client->model(),
            'generateContent',
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->instruction($trip, $candidates)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.4,
                    'maxOutputTokens' => 1200,
                    'responseSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'breaks' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'candidate_id' => ['type' => 'string'],
                                        'kind' => ['type' => 'string'],
                                        'title' => ['type' => 'string'],
                                        'reason' => ['type' => 'string'],
                                        'sequence' => ['type' => 'integer'],
                                    ],
                                    'required' => ['candidate_id', 'kind', 'title', 'reason', 'sequence'],
                                ],
                            ],
                        ],
                        'required' => ['breaks'],
                    ],
                ],
            ],
        );

        if ($response->failed()) {
            throw new AiGenerationException(
                GeminiResponseErrors::message($response, 'Unable to suggest breaks. Please try again.'),
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            return $this->fallbackBreaks($trip, $candidates);
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->fallbackBreaks($trip, $candidates);
        }

        return $this->mapBreaks($data, $candidates);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function gatherCandidates(Trip $trip): array
    {
        $layers = ['fuel', 'food', 'hotels', 'ev', 'viewpoints'];
        $candidates = [];

        foreach ($layers as $layer) {
            foreach ($this->amenitiesService->fetchForTrip($trip, $layer) as $place) {
                $id = (string) ($place['place_id'] ?? Str::uuid()->toString());
                $place['candidate_id'] = $id;
                $place['layer'] = $layer;
                $candidates[$id] = $place;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function mapBreaks(array $data, array $candidates): array
    {
        /** @var list<array<string, mixed>> $breaks */
        $breaks = is_array($data['breaks'] ?? null) ? $data['breaks'] : [];

        $mapped = [];

        foreach ($breaks as $break) {
            $candidateId = (string) ($break['candidate_id'] ?? '');

            if ($candidateId === '' || ! isset($candidates[$candidateId])) {
                continue;
            }

            $place = $candidates[$candidateId];

            $mapped[] = [
                'id' => (string) Str::uuid(),
                'kind' => (string) ($break['kind'] ?? 'break'),
                'title' => (string) ($break['title'] ?? $place['name']),
                'reason' => (string) ($break['reason'] ?? ''),
                'sequence' => (int) ($break['sequence'] ?? count($mapped) + 1),
                'label' => (string) $place['name'],
                'lat' => (float) $place['lat'],
                'lng' => (float) $place['lng'],
                'place_id' => $place['place_id'] ?? null,
                'category' => (string) ($place['category'] ?? ''),
                'address' => $place['address'] ?? null,
                'source' => 'ai_suggested',
            ];
        }

        usort($mapped, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        return $mapped;
    }

    /**
     * @param  array<string, array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function fallbackBreaks(Trip $trip, array $candidates = []): array
    {
        if ($candidates === []) {
            return [];
        }

        $selected = array_slice(array_values($candidates), 0, min(5, count($candidates)));

        return array_map(
            fn (array $place, int $index): array => [
                'id' => (string) Str::uuid(),
                'kind' => match ($place['layer'] ?? '') {
                    'hotels' => 'overnight',
                    'food' => 'meal',
                    'fuel', 'ev' => 'fuel',
                    default => 'break',
                },
                'title' => (string) $place['name'],
                'reason' => 'Suggested stop along your route.',
                'sequence' => $index + 1,
                'label' => (string) $place['name'],
                'lat' => (float) $place['lat'],
                'lng' => (float) $place['lng'],
                'place_id' => $place['place_id'] ?? null,
                'category' => (string) ($place['category'] ?? ''),
                'address' => $place['address'] ?? null,
                'source' => 'fallback',
            ],
            $selected,
            array_keys($selected),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $candidates
     */
    private function instruction(Trip $trip, array $candidates): string
    {
        $roadProfile = is_array($trip->road_profile) ? $trip->road_profile : [];
        $vehicle = VehicleClass::tryFrom((string) ($roadProfile['vehicle_class'] ?? 'car'))?->label() ?? 'Car';
        $fuel = FuelType::tryFrom((string) ($roadProfile['fuel_type'] ?? 'petrol'))?->label() ?? 'Petrol';
        $pace = DrivingPace::tryFrom((string) ($roadProfile['driving_pace'] ?? 'standard')) ?? DrivingPace::Standard;

        $origin = Trip::normalizeLocation($trip->getAttribute('origin'))['label'] ?? 'Origin';
        $destination = Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? 'Destination';
        $route = is_array($trip->route) ? $trip->route : [];
        $distance = (float) ($route['distance_km'] ?? 0);
        $durationHours = round(((int) ($route['duration_seconds'] ?? 0)) / 3600, 1);

        $candidateLines = collect(array_values($candidates))
            ->take(40)
            ->map(fn (array $place): string => sprintf(
                '- id=%s | layer=%s | name=%s | lat=%s | lng=%s',
                $place['candidate_id'],
                $place['layer'] ?? 'place',
                $place['name'],
                $place['lat'],
                $place['lng'],
            ))
            ->implode("\n");

        $foodPreference = (string) ($roadProfile['food_preference'] ?? 'any');
        $travelers = (int) $trip->travelers;
        $notes = trim((string) ($trip->notes ?? ''));

        return <<<PROMPT
You are TripPilot, a road trip planner. Suggest practical driving breaks using ONLY the candidate places listed below.

Trip: {$origin} → {$destination}
Distance: {$distance} km, driving time ~{$durationHours} hours
Vehicle: {$vehicle}, fuel: {$fuel}
Travelers: {$travelers}
Driving pace: {$pace->label()} (max ~{$pace->maxDriveHoursPerDay()} hours driving per day)
Food preference: {$foodPreference}
Notes: {$notes}

Rules:
- Pick 3 to 8 breaks spaced logically across the journey.
- Use ONLY candidate_id values from the list — do not invent places.
- Include fuel/charging stops for {$fuel} vehicles, meal breaks, and overnight hotel if the trip exceeds one driving day.
- kind must be one of: fuel, meal, rest, overnight, scenic, break
- Order by sequence starting at 1 along the route direction.

Candidates:
{$candidateLines}
PROMPT;
    }
}
