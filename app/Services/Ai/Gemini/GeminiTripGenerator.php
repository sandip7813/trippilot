<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\GeneratedItinerary;
use App\Exceptions\AiGenerationException;
use App\Support\BudgetBreakdownNormalizer;
use App\Support\GeminiResponseErrors;
use Illuminate\Support\Arr;
use JsonException;

class GeminiTripGenerator implements TripGenerator
{
    public function __construct(private GeminiClient $client) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function generate(string $prompt, array $context = []): GeneratedItinerary
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            throw new AiGenerationException('Gemini API key is not configured.');
        }

        $response = $this->client->post(
            $this->client->model(),
            'generateContent',
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->buildPrompt($prompt, $context)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->responseSchema(),
                ],
            ],
        );

        if ($response->failed()) {
            throw new AiGenerationException(
                GeminiResponseErrors::message($response, 'Unable to generate itinerary. Please try again.'),
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new AiGenerationException('AI returned an empty response.');
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiGenerationException('AI returned an invalid itinerary format.');
        }

        $itinerary = $this->mapToGeneratedItinerary($data);

        if ($itinerary->packingList === []) {
            $itinerary = new GeneratedItinerary(
                title: $itinerary->title,
                days: $itinerary->days,
                budget: $itinerary->budget,
                packingList: $this->fallbackPackingList($context),
                summary: $itinerary->summary,
            );
        }

        return $itinerary;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildPrompt(string $prompt, array $context): string
    {
        $lines = [
            'You are TripPilot, an expert travel planner. Create a detailed day-by-day itinerary as JSON.',
            '',
            'Trip details:',
        ];

        foreach ($this->contextLines($context) as $line) {
            $lines[] = "- {$line}";
        }

        if (($ragContext = trim((string) ($context['rag_context'] ?? ''))) !== '') {
            $lines[] = '';
            $lines[] = $ragContext;
            $lines[] = 'Use retrieved travel knowledge when relevant and prefer it over generic assumptions.';
        }

        if ($prompt !== '') {
            $lines[] = '';
            $lines[] = "Additional instructions: {$prompt}";
        }

        $lines[] = '';
        $lines[] = 'Requirements:';
        $lines[] = '- Match the exact number of days requested.';
        $lines[] = '- Include realistic times, activities, and practical notes.';
        $lines[] = '- Respect the travel style, budget, and traveler count.';
        $lines[] = '- For multi-city trips, group days by city and include inter-city travel on transition days.';
        $lines[] = '- For road trips, factor in driving segments between origin and destination.';
        $lines[] = '- Assign ISO dates (YYYY-MM-DD) to each day when start_date is provided.';
        $lines[] = '- Include city on each day and activity when the trip visits multiple cities.';
        $lines[] = '- Include budget.currency as INR with numeric INR amounts (no currency symbols in JSON).';
        $lines[] = '- budget.breakdown must include accommodation, food, transport, activities, and miscellaneous.';
        $lines[] = '- budget.estimated_total must be close to the sum of breakdown categories and respect the trip budget when provided.';
        $lines[] = '- Include packing_list with at least 10 practical items tailored to the destination climate, season, trip length, and planned activities.';
        $lines[] = '- packing_list must cover clothing layers, footwear, toiletries, documents, health items, and activity-specific gear.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function fallbackPackingList(array $context): array
    {
        $destination = Arr::get($context, 'destination.label', 'your destination');
        $dayCount = (int) Arr::get($context, 'day_count', 5);
        $travelers = (int) Arr::get($context, 'travelers', 1);

        return [
            "Weather-appropriate clothing for {$destination} (include layers)",
            'Comfortable walking shoes',
            'Light jacket or warm layers for cooler evenings',
            'Rain jacket or compact umbrella',
            'Government ID and trip booking confirmations',
            'Phone, charger, and power bank',
            'Personal medications and basic first-aid kit',
            'Sunscreen, sunglasses, and lip balm',
            'Reusable water bottle',
            'Toiletries and personal care items',
            "Daypack for {$dayCount}-day excursions",
            $travelers > 1 ? 'Shared power adapter or extension cord' : 'Travel adapter if needed',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function contextLines(array $context): array
    {
        $lines = [];

        if ($title = Arr::get($context, 'title')) {
            $lines[] = "Title: {$title}";
        }

        if ($typeLabel = Arr::get($context, 'type_label')) {
            $lines[] = "Planning mode: {$typeLabel}";
        }

        if ($styleLabel = Arr::get($context, 'travel_style_label')) {
            $lines[] = "Travel style: {$styleLabel}";
        }

        if ($originLabel = Arr::get($context, 'origin.label')) {
            $lines[] = "Origin: {$originLabel}";
        }

        if ($destinationLabel = Arr::get($context, 'destination.label')) {
            $lines[] = "Destination: {$destinationLabel}";
        }

        if (($routeMode = Arr::get($context, 'route_mode')) === 'multi_city') {
            $lines[] = 'Route mode: Multi-city';

            if ($routeLabel = Arr::get($context, 'route_summary.route_label')) {
                $lines[] = "Route: {$routeLabel}";
            }

            $waypoints = Arr::get($context, 'waypoints', []);

            if (is_array($waypoints) && $waypoints !== []) {
                $cityLabels = collect($waypoints)
                    ->map(fn (array $waypoint): ?string => Arr::get($waypoint, 'location.label'))
                    ->filter()
                    ->implode(' → ');

                if ($cityLabels !== '') {
                    $lines[] = "Cities: {$cityLabels}";
                }
            }
        }

        if ($startDate = Arr::get($context, 'start_date')) {
            $lines[] = "Start date: {$startDate}";
        }

        if ($endDate = Arr::get($context, 'end_date')) {
            $lines[] = "End date: {$endDate}";
        }

        if ($dayCount = Arr::get($context, 'day_count')) {
            $lines[] = "Number of days: {$dayCount}";
        }

        if ($travelers = Arr::get($context, 'travelers')) {
            $lines[] = "Travelers: {$travelers}";
        }

        if ($budget = Arr::get($context, 'budget')) {
            $currency = strtoupper((string) config('trippilot.currency', 'INR'));
            $lines[] = 'Budget: '.match ($currency) {
                'INR' => '₹'.number_format((float) $budget, 0),
                default => $currency.' '.number_format((float) $budget, 0),
            };
        }

        if ($notes = Arr::get($context, 'notes')) {
            $lines[] = "Notes: {$notes}";
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'days' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'day' => ['type' => 'integer'],
                            'date' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                            'waypoint_sequence' => ['type' => 'integer'],
                            'activities' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'time' => ['type' => 'string'],
                                        'title' => ['type' => 'string'],
                                        'notes' => ['type' => 'string'],
                                        'city' => ['type' => 'string'],
                                        'kind' => ['type' => 'string'],
                                    ],
                                    'required' => ['title'],
                                ],
                            ],
                        ],
                        'required' => ['day', 'title', 'activities'],
                    ],
                ],
                'budget' => [
                    'type' => 'object',
                    'properties' => [
                        'currency' => ['type' => 'string'],
                        'estimated_total' => ['type' => 'number'],
                        'breakdown' => [
                            'type' => 'object',
                            'properties' => [
                                'accommodation' => ['type' => 'number'],
                                'food' => ['type' => 'number'],
                                'transport' => ['type' => 'number'],
                                'activities' => ['type' => 'number'],
                                'miscellaneous' => ['type' => 'number'],
                            ],
                            'required' => [
                                'accommodation',
                                'food',
                                'transport',
                                'activities',
                                'miscellaneous',
                            ],
                        ],
                    ],
                    'required' => ['currency', 'estimated_total', 'breakdown'],
                ],
                'packing_list' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['title', 'summary', 'days', 'budget'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapToGeneratedItinerary(array $data): GeneratedItinerary
    {
        /** @var array<int, array<string, mixed>> $rawDays */
        $rawDays = is_array($data['days'] ?? null) ? $data['days'] : [];

        $days = collect($rawDays)
            ->values()
            ->map(function (array $day, int $index): array {
                /** @var array<int, array<string, mixed>> $activities */
                $activities = is_array($day['activities'] ?? null) ? $day['activities'] : [];

                return [
                    'day' => (int) ($day['day'] ?? $index + 1),
                    'date' => isset($day['date']) ? (string) $day['date'] : null,
                    'title' => (string) ($day['title'] ?? 'Day '.($index + 1)),
                    'city' => isset($day['city']) ? (string) $day['city'] : null,
                    'waypoint_sequence' => is_numeric($day['waypoint_sequence'] ?? null)
                        ? (int) $day['waypoint_sequence']
                        : null,
                    'activities' => collect($activities)
                        ->map(fn (array $activity): array => [
                            'time' => isset($activity['time']) ? (string) $activity['time'] : null,
                            'title' => (string) ($activity['title'] ?? 'Activity'),
                            'notes' => isset($activity['notes']) ? (string) $activity['notes'] : null,
                            'city' => isset($activity['city']) ? (string) $activity['city'] : null,
                            'kind' => isset($activity['kind']) ? (string) $activity['kind'] : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        /** @var array<string, mixed> $budget */
        $budget = is_array($data['budget'] ?? null)
            ? BudgetBreakdownNormalizer::normalize($data['budget'])
            : BudgetBreakdownNormalizer::normalize([]);

        /** @var array<int, string> $packingList */
        $packingList = is_array($data['packing_list'] ?? null)
            ? array_values(array_map(strval(...), $data['packing_list']))
            : [];

        return new GeneratedItinerary(
            title: (string) ($data['title'] ?? 'Trip itinerary'),
            days: $days,
            budget: $budget,
            packingList: $packingList,
            summary: (string) ($data['summary'] ?? ''),
        );
    }
}
