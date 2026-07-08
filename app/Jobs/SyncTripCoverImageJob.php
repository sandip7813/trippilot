<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\Trips\TripCoverImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncTripCoverImageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public string $tripId,
        public bool $onlyIfMissing = false,
    ) {}

    public function uniqueId(): string
    {
        return $this->tripId.($this->onlyIfMissing ? '-missing' : '-full');
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(TripCoverImageService $coverImageService): void
    {
        $trip = Trip::query()->find($this->tripId);

        if ($trip === null) {
            return;
        }

        if ($this->onlyIfMissing && filled($trip->cover_image_path)) {
            return;
        }

        $coverImageService->generateForTrip($trip);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Trip cover image job failed.', [
            'trip_id' => $this->tripId,
            'only_if_missing' => $this->onlyIfMissing,
            'message' => $exception?->getMessage(),
        ]);
    }
}
