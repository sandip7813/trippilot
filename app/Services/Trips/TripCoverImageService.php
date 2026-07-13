<?php

namespace App\Services\Trips;

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Data\TripCovers\TripCoverGenerationResult;
use App\Enums\TripCoverSource;
use App\Models\Trip;
use App\Services\TripCovers\TripCoverImageResizer;
use App\Services\TripCovers\TripCoverRotationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TripCoverImageService
{
    public function __construct(
        private TripCoverGenerator $coverGenerator,
        private TripCoverRotationService $rotationService,
        private TripCoverImageResizer $resizer,
    ) {}

    public function generateForTrip(Trip $trip, bool $tryNextSource = false): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));
        $destinationLabel = $destination['label'] ?? null;

        if ($destinationLabel === null || $destinationLabel === '') {
            return null;
        }

        $result = $this->resolveCover($trip, $destination, $tryNextSource);

        if ($result === null) {
            $this->markExhausted($trip);

            return null;
        }

        return $this->storeGenerationResult($trip, $result);
    }

    public function generateForTripIfMissing(Trip $trip): ?string
    {
        if (filled($trip->cover_image_path)) {
            return $trip->cover_image_path;
        }

        return $this->generateForTrip($trip, tryNextSource: false);
    }

    public function storeUpload(Trip $trip, UploadedFile $file): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $bannerSize = config('integrations.trip_covers.sizes.banner');
        $thumbSize = config('integrations.trip_covers.sizes.thumb');

        try {
            $bannerBytes = $file->getContent();

            if ($bannerBytes === false || $bannerBytes === '') {
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

            $result = new TripCoverGenerationResult(
                bytes: $bannerBytes,
                source: TripCoverSource::Upload,
                ref: 'upload:'.md5($bannerBytes),
                sourceIndex: -1,
                attribution: null,
                triedRefs: is_array($trip->cover_image_tried_refs) ? $trip->cover_image_tried_refs : [],
            );

            $this->persistCoverBytes($trip, $bannerBytes, $thumbBytes, $result);

            return $trip->fresh()->cover_image_path;
        } catch (\Throwable $exception) {
            Log::warning('Trip cover upload could not be saved.', [
                'trip_id' => (string) $trip->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function resolveCover(Trip $trip, array $destination, bool $tryNextSource): ?TripCoverGenerationResult
    {
        $driver = (string) config('integrations.trip_covers.driver', 'rotating');

        if ($driver === 'rotating') {
            $bannerSize = config('integrations.trip_covers.sizes.banner');

            return $this->rotationService->resolve(
                $trip,
                $destination,
                $trip->travel_style?->label(),
                (int) ($bannerSize['width'] ?? 1920),
                (int) ($bannerSize['height'] ?? 900),
                $tryNextSource,
            );
        }

        $bytes = $this->coverGenerator->generate(
            $destination,
            $trip->travel_style?->label(),
            (int) config('integrations.trip_covers.sizes.banner.width', 1920),
            (int) config('integrations.trip_covers.sizes.banner.height', 900),
        );

        if ($bytes === null || $bytes === '') {
            return null;
        }

        return new TripCoverGenerationResult(
            bytes: $bytes,
            source: TripCoverSource::tryFrom($driver) ?? TripCoverSource::Unsplash,
            ref: $driver.':legacy',
            sourceIndex: 0,
            attribution: null,
            triedRefs: is_array($trip->cover_image_tried_refs) ? $trip->cover_image_tried_refs : [],
        );
    }

    private function storeGenerationResult(Trip $trip, TripCoverGenerationResult $result): ?string
    {
        $thumbSize = config('integrations.trip_covers.sizes.thumb');

        try {
            $thumbBytes = $this->resizer->toJpegThumbnail(
                $result->bytes,
                (int) ($thumbSize['width'] ?? 384),
                (int) ($thumbSize['height'] ?? 512),
            );

            if ($thumbBytes === null) {
                return null;
            }

            $this->persistCoverBytes($trip, $result->bytes, $thumbBytes, $result);

            return $trip->fresh()->cover_image_path;
        } catch (\Throwable $exception) {
            Log::warning('Trip cover image could not be saved.', [
                'trip_id' => (string) $trip->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function persistCoverBytes(
        Trip $trip,
        string $bannerBytes,
        string $thumbBytes,
        TripCoverGenerationResult $result,
    ): void {
        $tripId = (string) $trip->id;
        $this->deleteExistingCovers($tripId);

        $bannerPath = $this->storagePath($tripId, 'banner');
        $thumbPath = $this->storagePath($tripId, 'thumb');

        Storage::disk('public')->put($bannerPath, $bannerBytes);
        Storage::disk('public')->put($thumbPath, $thumbBytes);

        $trip->update([
            'cover_image_path' => $bannerPath,
            'cover_image_thumb_path' => $thumbPath,
            'cover_image_source' => $result->source->value,
            'cover_image_source_index' => $result->sourceIndex,
            'cover_image_ref' => $result->ref,
            'cover_image_tried_refs' => $result->triedRefs,
            'cover_image_exhausted' => false,
            'cover_image_attribution' => $result->attribution,
        ]);
    }

    private function markExhausted(Trip $trip): void
    {
        $trip->update([
            'cover_image_exhausted' => true,
            'cover_image_path' => null,
            'cover_image_thumb_path' => null,
        ]);
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
        $this->deleteCoverFilesForTripId($tripId);
    }

    public function deleteForTrip(Trip $trip): void
    {
        $tripId = (string) $trip->id;

        $paths = [
            $this->storagePath($tripId, 'banner'),
            $this->storagePath($tripId, 'thumb'),
            "trip-covers/{$tripId}.jpg",
        ];

        if (is_string($trip->cover_image_path) && $trip->cover_image_path !== '') {
            $paths[] = $trip->cover_image_path;
        }

        if (is_string($trip->cover_image_thumb_path) && $trip->cover_image_thumb_path !== '') {
            $paths[] = $trip->cover_image_thumb_path;
        }

        foreach (array_unique($paths) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function deleteCoverFilesForTripId(string $tripId): void
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
