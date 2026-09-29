<?php

namespace App\Services\Trips;

use App\Models\Trip;
use App\Models\User;

/**
 * Builds the read-only data shape shown to public/guest visitors of an open
 * trip. This is a strict whitelist: it must never fall back to
 * Trip::toFrontend() or otherwise pass through owner-only data such as
 * budget, notes, chat, collaborators, or the owner's contact details.
 */
class OpenTripPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function overview(Trip $trip): array
    {
        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));
        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));
        $details = $trip->openTripDetails();
        $costModel = $trip->openTripCostModel();
        $maxGroupSize = $trip->maxGroupSize();

        return [
            'id' => (string) $trip->id,
            'title' => $trip->title,
            'type' => $trip->type->value,
            'type_label' => $trip->type->label(),
            'travel_style_label' => $trip->travel_style?->label(),
            'origin' => $origin !== null ? ['label' => $origin['label']] : null,
            'destination' => $destination !== null ? ['label' => $destination['label']] : null,
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'cover_image_url' => $trip->coverImageUrl(),
            'cover_image_thumb_url' => $trip->coverImageThumbUrl(),
            'is_past' => $trip->isPastTrip(),
            'is_joinable' => $trip->isJoinable(),
            'organizer' => $this->organizer($trip),
            'route' => $trip->publicRouteOverview(),
            'itinerary' => $trip->publicItineraryOverview(),
            'group' => [
                'category' => $details['category'] ?? null,
                'difficulty' => $details['difficulty'] ?? null,
                'requirements' => $details['requirements'] ?? null,
                'meeting_point' => $details['meeting_point'] ?? null,
                'rules' => $details['rules'] ?? null,
                'join_deadline' => $details['join_deadline'] ?? null,
                'max_group_size' => $maxGroupSize,
                'seats_left' => $maxGroupSize !== null ? max(0, $maxGroupSize - $trip->acceptedMemberCount()) : null,
                'member_count' => $trip->acceptedMemberCount(),
                'member_names_visible' => $trip->memberNamesVisible(),
                'cost_model' => $costModel?->value,
                'cost_model_label' => $costModel?->label(),
                'cost_amount' => $costModel?->hasAmount() ? ($details['cost_amount'] ?? null) : null,
                'cost_currency' => $costModel?->hasAmount() ? ($details['cost_currency'] ?? null) : null,
                'cost_inclusions' => $details['cost_inclusions'] ?? null,
            ],
        ];
    }

    /**
     * @return array{name: string}|null
     */
    private function organizer(Trip $trip): ?array
    {
        $owner = User::query()->find((int) $trip->user_id);

        if ($owner === null) {
            return null;
        }

        return [
            'name' => $owner->first_name,
        ];
    }
}
