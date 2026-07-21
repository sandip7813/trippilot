<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\Ai\ChatAssistant;
use App\Data\Ai\ChatResponse;
use App\Exceptions\AiGenerationException;
use App\Support\GeminiResponseErrors;
use Illuminate\Support\Arr;
use JsonException;

class GeminiChatAssistant implements ChatAssistant
{
    private const MAX_HISTORY_MESSAGES = 12;

    public function __construct(private GeminiClient $client) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $tripContext
     */
    public function chat(string $message, array $history, array $tripContext = []): ChatResponse
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            throw new AiGenerationException('Gemini API key is not configured.');
        }

        $response = $this->client->post(
            $this->client->model(),
            'generateContent',
            [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $this->systemInstruction($tripContext)],
                    ],
                ],
                'contents' => $this->buildContents($history, $message),
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.5,
                    'maxOutputTokens' => 1800,
                    'responseSchema' => $this->responseSchema(),
                ],
            ],
        );

        if ($response->failed()) {
            throw new AiGenerationException(
                GeminiResponseErrors::message($response, 'Unable to process your message. Please try again.'),
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
            throw new AiGenerationException('AI returned an invalid response format.');
        }

        $assistantMessage = trim((string) ($data['message'] ?? ''));

        if ($assistantMessage === '') {
            throw new AiGenerationException('AI returned an empty message.');
        }

        $patch = is_array($data['patch'] ?? null) ? $data['patch'] : null;

        if ($patch === []) {
            $patch = null;
        }

        return new ChatResponse(
            message: $assistantMessage,
            patch: $patch,
        );
    }

    /**
     * @param  array<string, mixed>  $tripContext
     */
    private function systemInstruction(array $tripContext): string
    {
        $lines = [
            'You are TripPilot, a helpful travel planning assistant.',
            'Answer questions about the user\'s trip and suggest practical changes.',
            'When the user asks to change the itinerary, notes, packing list, or budget, include a structured patch.',
            'Only include patch fields that should change. Omit patch entirely for pure Q&A.',
            'Never invent bookings or claim reservations were made.',
            'Keep replies concise and actionable.',
            '',
            'Current trip context:',
        ];

        foreach ($this->contextLines($tripContext) as $line) {
            $lines[] = "- {$line}";
        }

        if (($tripContext['has_itinerary'] ?? false) === false) {
            $lines[] = '';
            $lines[] = 'There is no generated itinerary yet. You can still answer planning questions and suggest notes.';
        }

        $lines[] = '';
        $lines[] = 'Patch rules:';
        $lines[] = '- patch.itinerary.days must use existing day numbers when editing, or new day numbers to append days.';
        $lines[] = '- patch.itinerary.summary, packing_list, and budget_breakdown replace those sections when provided.';
        $lines[] = '- patch.notes replaces trip notes when provided.';

        if (($tripContext['type'] ?? null) === 'road') {
            $lines[] = '';
            $lines[] = 'Road trip guidance:';
            $lines[] = '- waypoints are planned cities; stops are accepted break detours along the drive.';
            $lines[] = '- Do not claim to add map stops or recalculate routing; suggest ideas the user can add via the app.';
            $lines[] = '- Focus on pacing, safety, packing, food preferences, and practical driving advice.';
        }

        if (($ragContext = trim((string) ($tripContext['rag_context'] ?? ''))) !== '') {
            $lines[] = '';
            $lines[] = $ragContext;
            $lines[] = 'Use retrieved travel knowledge when relevant and prefer it over generic assumptions.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return list<array<string, mixed>>
     */
    private function buildContents(array $history, string $message): array
    {
        $contents = [];

        foreach (array_slice($history, -self::MAX_HISTORY_MESSAGES) as $entry) {
            $role = ($entry['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $content = trim((string) ($entry['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $content],
                ],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => trim($message)],
            ],
        ];

        return $contents;
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
        }

        if ($startDate = Arr::get($context, 'start_date')) {
            $lines[] = "Start date: {$startDate}";
        }

        if ($endDate = Arr::get($context, 'end_date')) {
            $lines[] = "End date: {$endDate}";
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

        if (($context['type'] ?? null) === 'road') {
            $this->appendRoadTripContextLines($lines, $context);
        }

        $itinerary = Arr::get($context, 'itinerary');

        if (is_array($itinerary)) {
            if (($summary = $itinerary['summary'] ?? '') !== '') {
                $lines[] = "Itinerary summary: {$summary}";
            }

            $days = is_array($itinerary['days'] ?? null) ? $itinerary['days'] : [];
            $lines[] = 'Itinerary days: '.count($days);

            foreach (array_slice($days, 0, 8) as $day) {
                if (! is_array($day)) {
                    continue;
                }

                $dayNumber = (int) ($day['day'] ?? 0);
                $dayTitle = (string) ($day['title'] ?? "Day {$dayNumber}");
                $city = isset($day['city']) ? ' ('.$day['city'].')' : '';
                $lines[] = "Day {$dayNumber}{$city}: {$dayTitle}";
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $context
     */
    private function appendRoadTripContextLines(array &$lines, array $context): void
    {
        $profile = Arr::get($context, 'road_profile');

        if (is_array($profile)) {
            if ($vehicle = Arr::get($profile, 'vehicle_class')) {
                $lines[] = "Vehicle: {$vehicle}";
            }

            if ($fuel = Arr::get($profile, 'fuel_type')) {
                $lines[] = "Fuel type: {$fuel}";
            }

            if ($pace = Arr::get($profile, 'driving_pace')) {
                $lines[] = "Driving pace: {$pace}";
            }

            if (($profile['avoid_tolls'] ?? false) === true) {
                $lines[] = 'Prefers routes without tolls';
            }

            if (($profile['avoid_highways'] ?? false) === true) {
                $lines[] = 'Prefers avoiding highways';
            }

            if ($evRange = Arr::get($profile, 'ev_range_km')) {
                $lines[] = "EV range: {$evRange} km";
            }
        }

        $route = Arr::get($context, 'route');

        if (is_array($route)) {
            if ($distance = Arr::get($route, 'distance_km')) {
                $lines[] = 'Route distance: '.number_format((float) $distance, 1).' km';
            }

            if ($duration = Arr::get($route, 'duration_seconds')) {
                $hours = round(((int) $duration) / 3600, 1);
                $lines[] = "Estimated drive time: {$hours} hours";
            }

            if (($route['has_tolls'] ?? false) === true) {
                $lines[] = 'Route includes tolls';
            }
        }

        $stops = Arr::get($context, 'stops');

        if (is_array($stops) && $stops !== []) {
            $lines[] = 'Planned stops: '.count($stops);

            foreach (array_slice($stops, 0, 8) as $index => $stop) {
                if (! is_array($stop)) {
                    continue;
                }

                $label = (string) ($stop['label'] ?? 'Stop');
                $lines[] = 'Stop '.($index + 1).": {$label}";
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'patch' => [
                    'type' => 'object',
                    'properties' => [
                        'notes' => ['type' => 'string'],
                        'itinerary' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => ['type' => 'string'],
                                'packing_list' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'budget_breakdown' => [
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
                                        ],
                                    ],
                                ],
                                'days' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'day' => ['type' => 'integer'],
                                            'date' => ['type' => 'string'],
                                            'title' => ['type' => 'string'],
                                            'city' => ['type' => 'string'],
                                            'activities' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'time' => ['type' => 'string'],
                                                        'title' => ['type' => 'string'],
                                                        'notes' => ['type' => 'string'],
                                                        'city' => ['type' => 'string'],
                                                    ],
                                                    'required' => ['title'],
                                                ],
                                            ],
                                        ],
                                        'required' => ['day', 'title'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['message'],
        ];
    }
}
