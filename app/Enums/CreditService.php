<?php

namespace App\Enums;

/**
 * Billable AI actions. Each case has a credit cost that super admins can
 * change from the admin panel; defaultCredits() is the fallback used when no
 * cost has been stored yet, so a missing row never makes an action free.
 */
enum CreditService: string
{
    case ItineraryGeneration = 'itinerary_generation';
    case ItineraryRegeneration = 'itinerary_regeneration';
    case TripChatMessage = 'trip_chat_message';
    case AssistantMessage = 'assistant_message';
    case RoadBreakSuggestions = 'road_break_suggestions';

    public function label(): string
    {
        return match ($this) {
            self::ItineraryGeneration => 'Itinerary generation',
            self::ItineraryRegeneration => 'Itinerary regeneration',
            self::TripChatMessage => 'Trip chat message',
            self::AssistantMessage => 'Travel assistant message',
            self::RoadBreakSuggestions => 'Road-trip break suggestions',
        };
    }

    public function defaultCredits(): int
    {
        return match ($this) {
            self::ItineraryGeneration => 12,
            self::ItineraryRegeneration => 10,
            self::TripChatMessage => 2,
            self::AssistantMessage => 2,
            self::RoadBreakSuggestions => 3,
        };
    }
}
