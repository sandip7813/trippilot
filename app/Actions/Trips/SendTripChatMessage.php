<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\ChatAssistant;
use App\Models\Trip;
use App\Services\Trips\TripAiContextBuilder;
use App\Support\Trips\TripItineraryPatcher;
use Illuminate\Support\Str;

class SendTripChatMessage
{
    public function __construct(
        private ChatAssistant $chatAssistant,
        private TripAiContextBuilder $contextBuilder,
        private TripItineraryPatcher $itineraryPatcher,
    ) {}

    /**
     * @return array{trip: Trip, patch_applied: bool}
     */
    public function __invoke(Trip $trip, string $message): array
    {
        $history = Trip::normalizeChatMessages($trip->getAttribute('chat_messages'));
        $tripContext = $this->contextBuilder->build($trip, $message);

        $response = $this->chatAssistant->chat($message, $history, $tripContext);

        $userMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => trim($message),
            'created_at' => now()->toIso8601String(),
        ];

        $patchApplied = false;
        $updates = [];

        if ($response->patch !== null) {
            $patchUpdates = $this->itineraryPatcher->apply($trip, $response->patch);

            if ($patchUpdates['itinerary'] !== null) {
                $updates['itinerary'] = $patchUpdates['itinerary'];
                $patchApplied = true;
            }

            if ($patchUpdates['notes'] !== null) {
                $updates['notes'] = $patchUpdates['notes'];
                $patchApplied = true;
            }
        }

        $assistantMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $response->message,
            'patch_applied' => $patchApplied,
            'created_at' => now()->toIso8601String(),
        ];

        $updates['chat_messages'] = [
            ...$history,
            $userMessage,
            $assistantMessage,
        ];

        $trip->update($updates);

        return [
            'trip' => $trip->fresh(),
            'patch_applied' => $patchApplied,
        ];
    }
}
