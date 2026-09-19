<?php

namespace App\Enums;

enum AiUsageFeature: string
{
    case ItineraryGeneration = 'itinerary_generation';
    case TripChat = 'trip_chat';
    case AssistantChat = 'assistant_chat';
    case RoadBreakSuggestions = 'road_break_suggestions';
    case CoverPrompt = 'cover_prompt';
    case CoverSearch = 'cover_search';
    case CoverImage = 'cover_image';
    case Embedding = 'embedding';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ItineraryGeneration => 'Itinerary generation',
            self::TripChat => 'Trip chat',
            self::AssistantChat => 'Travel assistant',
            self::RoadBreakSuggestions => 'Road trip breaks',
            self::CoverPrompt => 'Cover prompts',
            self::CoverSearch => 'Cover search',
            self::CoverImage => 'Cover images',
            self::Embedding => 'Knowledge embeddings',
            self::Other => 'Other',
        };
    }
}
