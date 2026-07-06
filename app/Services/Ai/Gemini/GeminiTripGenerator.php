<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\GeneratedItinerary;
use App\Exceptions\AiGenerationException;
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
            throw new AiGenerationException('Unable to generate itinerary. Please try again.');
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

        return $this->mapToGeneratedItinerary($data);
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

        if ($prompt !== '') {
            $lines[] = '';
            $lines[] = "Additional instructions: {$prompt}";
        }

        $lines[] = '';
        $lines[] = 'Requirements:';
        $lines[] = '- Match the exact number of days requested.';
        $lines[] = '- Include realistic times, activities, and practical notes.';
        $lines[] = '- Respect the travel style, budget, and traveler count.';
        $lines[] = '- For road trips, factor in driving segments between origin and destination.';
        $lines[] = '- Assign ISO dates (YYYY-MM-DD) to each day when start_date is provided.';

        return implode("\n", $lines);
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
            $lines[] = 'Budget: $'.number_format((float) $budget, 0);
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
                            'activities' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'time' => ['type' => 'string'],
                                        'title' => ['type' => 'string'],
                                        'notes' => ['type' => 'string'],
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
                        'estimated_total' => ['type' => 'number'],
                        'breakdown' => ['type' => 'object'],
                    ],
                ],
                'packing_list' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['title', 'summary', 'days'],
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
                    'activities' => collect($activities)
                        ->map(fn (array $activity): array => [
                            'time' => isset($activity['time']) ? (string) $activity['time'] : null,
                            'title' => (string) ($activity['title'] ?? 'Activity'),
                            'notes' => isset($activity['notes']) ? (string) $activity['notes'] : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        /** @var array<string, mixed> $budget */
        $budget = is_array($data['budget'] ?? null) ? $data['budget'] : [];

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
