<?php

namespace App\Support\Trips;

use App\Models\Trip;
use App\Support\BudgetBreakdownNormalizer;

class TripItineraryPatcher
{
    /**
     * @param  array<string, mixed>  $patch
     * @return array{itinerary: array<string, mixed>|null, notes: string|null}
     */
    public function apply(Trip $trip, array $patch): array
    {
        $updates = [
            'itinerary' => null,
            'notes' => null,
        ];

        if (isset($patch['notes']) && is_string($patch['notes'])) {
            $updates['notes'] = $patch['notes'];
        }

        $itineraryPatch = is_array($patch['itinerary'] ?? null) ? $patch['itinerary'] : null;

        if ($itineraryPatch === null) {
            return $updates;
        }

        $current = Trip::coerceStructuredArray($trip->getAttribute('itinerary')) ?? Trip::emptyItinerary();
        $merged = $current;

        if (isset($itineraryPatch['summary']) && is_string($itineraryPatch['summary'])) {
            $merged['summary'] = $itineraryPatch['summary'];
        }

        if (isset($itineraryPatch['packing_list']) && is_array($itineraryPatch['packing_list'])) {
            $merged['packing_list'] = array_values(array_map(strval(...), $itineraryPatch['packing_list']));
        }

        if (isset($itineraryPatch['budget_breakdown']) && is_array($itineraryPatch['budget_breakdown'])) {
            $merged['budget_breakdown'] = BudgetBreakdownNormalizer::normalize($itineraryPatch['budget_breakdown']);
        }

        if (isset($itineraryPatch['days']) && is_array($itineraryPatch['days'])) {
            $merged['days'] = $this->mergeDays(
                is_array($current['days'] ?? null) ? $current['days'] : [],
                $itineraryPatch['days'],
            );
        }

        $updates['itinerary'] = $merged;

        return $updates;
    }

    /**
     * @param  list<array<string, mixed>>  $existingDays
     * @param  list<array<string, mixed>>  $patchedDays
     * @return list<array<string, mixed>>
     */
    private function mergeDays(array $existingDays, array $patchedDays): array
    {
        $byDay = [];

        foreach ($existingDays as $day) {
            if (! is_array($day)) {
                continue;
            }

            $dayNumber = (int) ($day['day'] ?? 0);

            if ($dayNumber > 0) {
                $byDay[$dayNumber] = $day;
            }
        }

        foreach ($patchedDays as $day) {
            if (! is_array($day)) {
                continue;
            }

            $dayNumber = (int) ($day['day'] ?? 0);

            if ($dayNumber <= 0) {
                continue;
            }

            $byDay[$dayNumber] = array_replace($byDay[$dayNumber] ?? ['day' => $dayNumber], $day);
        }

        ksort($byDay);

        return array_values($byDay);
    }
}
