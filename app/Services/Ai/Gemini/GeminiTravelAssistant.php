<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\Ai\TravelAssistant;
use App\Data\Ai\AssistantChatResponse;
use App\Enums\AiUsageFeature;
use App\Exceptions\AiGenerationException;
use App\Support\GeminiResponseErrors;
use Illuminate\Support\Arr;

class GeminiTravelAssistant implements TravelAssistant
{
    private const int MAX_HISTORY_MESSAGES = 10;

    private const int MAX_OUTPUT_TOKENS = 2048;

    public function __construct(private GeminiClient $client) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     */
    public function chat(string $message, array $history, array $context = []): AssistantChatResponse
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
                        ['text' => $this->systemInstruction($context)],
                    ],
                ],
                'contents' => $this->buildContents($history, $message),
                'generationConfig' => [
                    'temperature' => 0.6,
                    'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
                ],
            ],
            AiUsageFeature::AssistantChat,
        );

        if ($response->failed()) {
            throw new AiGenerationException(
                GeminiResponseErrors::message($response, 'Unable to process your message. Please try again.'),
            );
        }

        $assistantMessage = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));

        if ($assistantMessage === '') {
            throw new AiGenerationException('AI returned an empty response.');
        }

        if (data_get($response->json(), 'candidates.0.finishReason') === 'MAX_TOKENS') {
            $assistantMessage .= "\n\n(Response trimmed — try a shorter or more specific question.)";
        }

        return new AssistantChatResponse(message: $assistantMessage);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function systemInstruction(array $context): string
    {
        $lines = [
            'You are TripPilot, a friendly travel planning assistant focused on India and nearby destinations.',
            'Help users research destinations, compare options, plan budgets, packing, routes, seasons, and travel styles.',
            'Answer clearly and practically. Use bullet points when listing options.',
            'Do not claim to book tickets, hotels, or make reservations.',
            'If you lack specific data, say so and give general guidance.',
            'Default currency is INR unless the user specifies otherwise.',
            '',
            'User context:',
        ];

        if ($name = Arr::get($context, 'user_name')) {
            $lines[] = "- Name: {$name}";
        }

        if (is_array($homeCity = Arr::get($context, 'home_city')) && filled($homeCity['label'] ?? null)) {
            $lines[] = '- Home city: '.$homeCity['label'];
        }

        $recentTrips = Arr::get($context, 'recent_trips');

        if (is_array($recentTrips) && $recentTrips !== []) {
            $lines[] = '- Recent trips (summary only — do not assume full itineraries):';

            foreach ($recentTrips as $trip) {
                if (! is_array($trip)) {
                    continue;
                }

                $label = (string) ($trip['title'] ?? 'Trip');
                $destination = (string) ($trip['destination'] ?? 'Unknown destination');
                $lines[] = "  • {$label} → {$destination}";
            }
        }

        if (($ragContext = trim((string) ($context['rag_context'] ?? ''))) !== '') {
            $lines[] = '';
            $lines[] = $ragContext;
            $lines[] = 'Prefer retrieved travel knowledge when relevant.';
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
}
