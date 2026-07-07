<?php

namespace App\Services\Trips;

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Models\Trip;
use App\Services\TripCovers\TripCoverImageResizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TripCoverImageService
{
    public function __construct(
        private TripCoverGenerator $coverGenerator,
        private TripCoverImageResizer $resizer,
    ) {}

    public function generateForTrip(Trip $trip): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));
        $destinationLabel = $destination['label'] ?? null;

        if ($destinationLabel === null || $destinationLabel === '') {
            return null;
        }

        return $this->storeCoverImages($trip, $destination);
    }

    public function generateForTripIfMissing(Trip $trip): ?string
    {
        if (filled($trip->cover_image_path)) {
            return $trip->cover_image_path;
        }

        return $this->generateForTrip($trip);
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function storeCoverImages(Trip $trip, array $destination): ?string
    {
        $bannerSize = config('integrations.trip_covers.sizes.banner');
        $thumbSize = config('integrations.trip_covers.sizes.thumb');

        try {
            $bannerBytes = $this->coverGenerator->generate(
                $destination,
                $trip->travel_style?->label(),
                (int) ($bannerSize['width'] ?? 1920),
                (int) ($bannerSize['height'] ?? 900),
            );

            if ($bannerBytes === null) {
                return null;
            }

            $thumbBytes = $this->resizer->toJpegThumbnail(
                $bannerBytes,
                (int) ($thumbSize['width'] ?? 384),
                (int) ($thumbSize['height'] ?? 512),
            );

            if ($thumbBytes === null) {
                return null;
            }

            $tripId = (string) $trip->id;
            $this->deleteExistingCovers($tripId);

            $bannerPath = $this->storagePath($tripId, 'banner');
            $thumbPath = $this->storagePath($tripId, 'thumb');

            Storage::disk('public')->put($bannerPath, $bannerBytes);
            Storage::disk('public')->put($thumbPath, $thumbBytes);

            $trip->update([
                'cover_image_path' => $bannerPath,
                'cover_image_thumb_path' => $thumbPath,
            ]);

            return $bannerPath;
        } catch (\Throwable $exception) {
            Log::warning('Trip cover image could not be saved.', [
                'trip_id' => (string) $trip->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isEnabled(): bool
    {
        if (! filter_var(config('integrations.trip_covers.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return config('integrations.trip_covers.driver') !== 'none';
    }

    private function deleteExistingCovers(string $tripId): void
    {
        $paths = [
            $this->storagePath($tripId, 'banner'),
            $this->storagePath($tripId, 'thumb'),
            "trip-covers/{$tripId}.jpg",
        ];

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function storagePath(string $tripId, string $variant): string
    {
        return "trip-covers/{$tripId}-{$variant}.jpg";
    }
}
