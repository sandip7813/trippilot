<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\GeneratedItinerary;
use App\Enums\CreditService;
use App\Enums\TripStatus;
use App\Exceptions\AiGenerationException;
use App\Models\Trip;
use App\Models\User;
use App\Services\Ai\GeminiUsageLimiter;
use App\Services\Credits\AiCreditCharger;
use App\Services\Trips\TripAiContextBuilder;

class GenerateTripItinerary
{
    public function __construct(
        private TripGenerator $tripGenerator,
        private SyncTripCoverImage $syncTripCoverImage,
        private TripAiContextBuilder $contextBuilder,
        private GeminiUsageLimiter $usageLimiter,
        private AiCreditCharger $creditCharger,
    ) {}

    public function __invoke(Trip $trip, User $user): Trip
    {
        if (! $this->usageLimiter->hasRemaining($user)) {
            throw new AiGenerationException(__(
                "You've reached today's AI usage limit (:limit requests). Please try again tomorrow.",
                ['limit' => $this->usageLimiter->dailyLimit()],
            ));
        }

        $service = $trip->hasGeneratedItinerary()
            ? CreditService::ItineraryRegeneration
            : CreditService::ItineraryGeneration;

        $generated = $this->creditCharger->charge($user, $service, function () use ($trip, $user): GeneratedItinerary {
            $this->usageLimiter->increment($user);

            return $this->tripGenerator->generate('', $this->contextBuilder->build($trip));
        }, (string) $trip->id);

        $trip->update([
            'itinerary' => $generated->toTripItinerary(),
            'status' => TripStatus::Planned,
        ]);

        $trip = $trip->fresh();

        ($this->syncTripCoverImage)($trip, onlyIfMissing: true);

        return $trip;
    }
}
