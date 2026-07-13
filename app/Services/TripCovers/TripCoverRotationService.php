<?php

namespace App\Services\TripCovers;

use App\Data\TripCovers\TripCoverCandidate;
use App\Data\TripCovers\TripCoverGenerationResult;
use App\Enums\TripCoverSource;
use App\Models\Trip;

class TripCoverRotationService
{
    public function __construct(
        private WikipediaPlaceImageClient $wikipedia,
        private CommonsGeoImageClient $commonsGeo,
        private CommonsTextImageClient $commonsText,
        private UnsplashTripCoverGenerator $unsplashGenerator,
        private PollinationsTripCoverGenerator $pollinationsGenerator,
        private TripCoverImageDownloader $downloader,
    ) {}

    public function resolve(
        Trip $trip,
        array $destination,
        ?string $travelStyle,
        int $width,
        int $height,
        bool $tryNextSource,
    ): ?TripCoverGenerationResult {
        $trip->refresh();

        $ladder = TripCoverSource::rotationLadder();
        /** @var list<string> $triedRefs */
        $triedRefs = $this->normalizeTriedRefs($trip->cover_image_tried_refs);

        if ($tryNextSource) {
            $triedRefs = $this->appendExistingCoverRefs($trip, $triedRefs);
        }

        $startIndex = 0;

        for ($index = $startIndex; $index < count($ladder); $index++) {
            $source = $ladder[$index];

            if (in_array($source, [TripCoverSource::Unsplash, TripCoverSource::Pollinations], true)) {
                foreach ($this->generatorCandidatesForSource($source, $destination, $travelStyle, $width, $height, $triedRefs) as $candidate) {
                    if (in_array($candidate->ref, $triedRefs, true)) {
                        continue;
                    }

                    $bytes = $this->downloader->download($candidate->imageUrl);

                    if ($bytes === null || $bytes === '') {
                        $triedRefs[] = $candidate->ref;

                        continue;
                    }

                    return new TripCoverGenerationResult(
                        bytes: $bytes,
                        source: $source,
                        ref: $candidate->ref,
                        sourceIndex: $index,
                        attribution: $candidate->attribution,
                        triedRefs: [...$triedRefs, $candidate->ref],
                    );
                }

                continue;
            }

            foreach ($this->urlCandidatesForSource($source, $destination, $width) as $candidate) {
                if (in_array($candidate->ref, $triedRefs, true)) {
                    continue;
                }

                $bytes = $this->downloader->download($candidate->imageUrl);

                if ($bytes === null) {
                    $triedRefs[] = $candidate->ref;

                    continue;
                }

                return new TripCoverGenerationResult(
                    bytes: $bytes,
                    source: $source,
                    ref: $candidate->ref,
                    sourceIndex: $index,
                    attribution: $candidate->attribution,
                    triedRefs: [...$triedRefs, $candidate->ref],
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    private function urlCandidatesForSource(TripCoverSource $source, array $destination, int $width): array
    {
        return match ($source) {
            TripCoverSource::Wikipedia => $this->wikipedia->candidates($destination),
            TripCoverSource::CommonsGeo => $this->commonsGeo->candidates($destination, $width),
            TripCoverSource::CommonsText => $this->commonsText->candidates($destination, $width),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $destination
     * @param  list<string>  $triedRefs
     * @return list<TripCoverCandidate>
     */
    private function generatorCandidatesForSource(
        TripCoverSource $source,
        array $destination,
        ?string $travelStyle,
        int $width,
        int $height,
        array $triedRefs,
    ): array {
        $pollinationsAttempts = count(array_filter(
            $triedRefs,
            static fn (string $ref): bool => str_starts_with($ref, 'pollinations:'),
        ));

        return match ($source) {
            TripCoverSource::Unsplash => $this->unsplashGenerator->candidates($destination, $travelStyle),
            TripCoverSource::Pollinations => $this->pollinationsGenerator->candidates(
                $destination,
                $travelStyle,
                $width,
                $height,
                $pollinationsAttempts,
            ),
            default => [],
        };
    }

    /**
     * @param  list<string>|mixed  $triedRefs
     * @return list<string>
     */
    private function normalizeTriedRefs(mixed $triedRefs): array
    {
        if (! is_array($triedRefs)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $ref): string => is_string($ref) ? $ref : '',
            $triedRefs,
        )));
    }

    /**
     * @param  list<string>  $triedRefs
     * @return list<string>
     */
    private function appendExistingCoverRefs(Trip $trip, array $triedRefs): array
    {
        foreach ($this->existingCoverRefs($trip) as $ref) {
            if (! in_array($ref, $triedRefs, true)) {
                $triedRefs[] = $ref;
            }
        }

        return $triedRefs;
    }

    /**
     * @return list<string>
     */
    private function existingCoverRefs(Trip $trip): array
    {
        $refs = [];

        if (is_string($trip->cover_image_ref) && $trip->cover_image_ref !== '') {
            $refs[] = $trip->cover_image_ref;
        }

        if (is_string($trip->cover_image_path) && $trip->cover_image_path !== '') {
            $refs[] = 'legacy:'.md5($trip->cover_image_path);
        }

        if (is_string($trip->cover_image_source) && $trip->cover_image_source !== '') {
            $refs[] = $trip->cover_image_source.':legacy';
        }

        return array_values(array_unique($refs));
    }
}
