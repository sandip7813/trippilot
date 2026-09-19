<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\TripGenerator;
use App\Enums\TripStatus;
use App\Exceptions\AiGenerationException;
use App\Models\Trip;
use App\Models\User;
use App\Services\Ai\GeminiUsageLimiter;
use App\Services\Trips\TripAiContextBuilder;

class GenerateTripItinerary
{
    public function __construct(
        private TripGenerator $tripGenerator,
        private SyncTripCoverImage $syncTripCoverImage,
        private TripAiContextBuilder $contextBuilder,
        private GeminiUsageLimiter $usageLimiter,
    ) {}

    public function __invoke(Trip $trip, User $user): Trip
    {
        if (! $this->usageLimiter->hasRemaining($user)) {
            throw new AiGenerationException(__(
                "You've reached today's AI usage limit (:limit requests). Please try again tomorrow.",
                ['limit' => $this->usageLimiter->dailyLimit()],
            ));
        }

        $this->usageLimiter->increment($user);

        $generated = $this->tripGenerator->generate('', $this->contextBuilder->build($trip));

        $trip->update([
            'itinerary' => $generated->toTripItinerary(),
            'status' => TripStatus::Planned,
        ]);

        $trip = $trip->fresh();

        ($this->syncTripCoverImage)($trip, onlyIfMissing: true);

        return $trip;
    }
}
