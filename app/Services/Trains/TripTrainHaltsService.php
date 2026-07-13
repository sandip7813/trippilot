<?php

namespace App\Services\Trains;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TripTrainHaltsService
{
    public function __construct(private RailRadarClient $client) {}

    /**
     * @param  array{
     *     from: string,
     *     to: string,
     *     date?: string|null,
     *     from_sequence?: int|null,
     *     to_sequence?: int|null,
     * }  $segment
     * @return array<string, mixed>
     */
    public function forSegment(string $trainNumber, array $segment): array
    {
        if (! filled(config('integrations.trains.drivers.railradar.api_key'))) {
            return [
                'available' => false,
                'message' => 'Train halts are unavailable without a RailRadar API key.',
            ];
        }

        $fromCode = strtoupper($segment['from']);
        $toCode = strtoupper($segment['to']);
        $date = $segment['date'] ?? null;
        $fromSequence = isset($segment['from_sequence']) ? (int) $segment['from_sequence'] : null;
        $toSequence = isset($segment['to_sequence']) ? (int) $segment['to_sequence'] : null;

        $cacheTtl = (int) config('integrations.trains.cache_ttl', 43200);
        $cacheKey = sprintf(
            'trip_train_halts:v3:%s:%s:%s:%s:%s:%s',
            $trainNumber,
            $fromCode,
            $toCode,
            $date ?? 'any',
            $fromSequence ?? 'auto',
            $toSequence ?? 'auto',
        );

        return Cache::remember($cacheKey, $cacheTtl, function () use (
            $trainNumber,
            $fromCode,
            $toCode,
            $date,
            $fromSequence,
            $toSequence,
        ): array {
            $stops = $this->fetchStops($trainNumber, $date);

            if ($stops === null) {
                return [
                    'available' => false,
                    'message' => 'Could not load halt details for this train right now.',
                ];
            }

            $segmentStops = $this->filterSegmentStops(
                $stops,
                $fromCode,
                $toCode,
                $fromSequence,
                $toSequence,
            );

            if ($segmentStops === []) {
                return [
                    'available' => false,
                    'message' => 'No halts found between the selected stations for this train.',
                ];
            }

            return [
                'available' => true,
                'train_number' => $trainNumber,
                'from_code' => $fromCode,
                'to_code' => $toCode,
                'travel_date' => $date,
                'halt_count' => max(0, count($segmentStops) - 2),
                'halts' => $segmentStops,
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchStops(string $trainNumber, ?string $date): ?array
    {
        $attempts = [];

        if ($date !== null) {
            $attempts[] = fn (): Response => $this->client->trainLive($trainNumber, $date, true);
            $attempts[] = fn (): Response => $this->client->trainSchedule($trainNumber, $date);
        }

        $attempts[] = fn (): Response => $this->client->trainLive($trainNumber, null, true);
        $attempts[] = fn (): Response => $this->client->trainSchedule($trainNumber);

        foreach ($attempts as $attempt) {
            $response = $attempt();

            if ($response->failed()) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $data = $response->json('data') ?? [];
            $rawStops = $this->extractRawStops($data);

            if ($rawStops === null || $rawStops === []) {
                continue;
            }

            $stops = $this->normalizeStops($rawStops);

            if ($stops !== []) {
                return $stops;
            }
        }

        Log::warning('RailRadar train halt lookup failed.', [
            'train' => $trainNumber,
            'date' => $date,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>|null
     */
    private function extractRawStops(array $data): ?array
    {
        foreach (['route', 'stops', 'schedule', 'halts'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        if (isset($data['train']) && is_array($data['train'])) {
            /** @var array<string, mixed> $train */
            $train = $data['train'];

            foreach (['route', 'stops', 'schedule', 'halts'] as $key) {
                if (isset($train[$key]) && is_array($train[$key])) {
                    return $train[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function normalizeStops(array $stops): array
    {
        return collect($stops)
            ->map(function (array $stop): ?array {
                /** @var array<string, mixed> $station */
                $station = is_array($stop['station'] ?? null) ? $stop['station'] : [];

                $code = strtoupper((string) (
                    $stop['stationCode']
                    ?? $stop['code']
                    ?? $station['code']
                    ?? ''
                ));
                $name = (string) (
                    $stop['stationName']
                    ?? $stop['name']
                    ?? $station['name']
                    ?? ''
                );

                if ($code === '' && $name === '') {
                    return null;
                }

                $sequence = is_numeric($stop['sequence'] ?? null) ? (int) $stop['sequence'] : null;
                $haltMinutes = is_numeric($stop['haltMinutes'] ?? $stop['halt_minutes'] ?? $stop['halt'] ?? null)
                    ? (int) $stop['haltMinutes']
                    : (is_numeric($stop['haltTime'] ?? null) ? (int) $stop['haltTime'] : null);

                $arrival = $this->extractStopTime($stop, 'arrival');
                $departure = $this->extractStopTime($stop, 'departure');
                [$arrival, $departure] = $this->reconcileStopTimes($stop, $arrival, $departure, $haltMinutes);

                $day = is_numeric($stop['day'] ?? $stop['dayCount'] ?? null)
                    ? (int) ($stop['day'] ?? $stop['dayCount'])
                    : null;

                return [
                    'sequence' => $sequence,
                    'code' => $code,
                    'name' => $name,
                    'arrival' => $arrival,
                    'departure' => $departure,
                    'day' => $day,
                    'distance_km' => is_numeric($stop['distance'] ?? null) ? (float) $stop['distance'] : null,
                    'platform' => isset($stop['platform']) ? (string) $stop['platform'] : null,
                    'halt_minutes' => $haltMinutes,
                    'is_halt' => array_key_exists('isHalt', $stop) ? (bool) $stop['isHalt'] : true,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function filterSegmentStops(
        array $stops,
        string $fromCode,
        string $toCode,
        ?int $fromSequence,
        ?int $toSequence,
    ): array {
        if ($fromSequence !== null && $toSequence !== null) {
            $segmentStops = $this->filterBySequence($stops, $fromSequence, $toSequence, $fromCode, $toCode);

            if ($segmentStops !== []) {
                return $segmentStops;
            }
        }

        return $this->filterByStationCodes($stops, $fromCode, $toCode);
    }

    /**
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function filterBySequence(
        array $stops,
        int $fromSequence,
        int $toSequence,
        string $fromCode,
        string $toCode,
    ): array {
        $minSequence = min($fromSequence, $toSequence);
        $maxSequence = max($fromSequence, $toSequence);

        $segmentStops = collect($stops)
            ->filter(function (array $stop) use ($minSequence, $maxSequence): bool {
                $sequence = $stop['sequence'] ?? null;

                return $sequence !== null
                    && $sequence >= $minSequence
                    && $sequence <= $maxSequence;
            })
            ->values();

        return $segmentStops
            ->map(function (array $stop, int $index) use ($fromCode, $toCode, $segmentStops): array {
                return $this->markSegmentEndpoints(
                    $stop,
                    $fromCode,
                    $toCode,
                    $index === 0,
                    $index === $segmentStops->count() - 1,
                );
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    private function filterByStationCodes(array $stops, string $fromCode, string $toCode): array
    {
        $fromIndex = null;
        $toIndex = null;

        foreach ($stops as $index => $stop) {
            if ($fromIndex === null && ($stop['code'] ?? '') === $fromCode) {
                $fromIndex = $index;
            }

            if (($stop['code'] ?? '') === $toCode) {
                $toIndex = $index;
            }
        }

        if ($fromIndex === null || $toIndex === null) {
            return [];
        }

        $start = min($fromIndex, $toIndex);
        $end = max($fromIndex, $toIndex);

        return collect($stops)
            ->slice($start, $end - $start + 1)
            ->values()
            ->map(function (array $stop, int $index) use ($fromCode, $toCode, $start, $end): array {
                return $this->markSegmentEndpoints(
                    $stop,
                    $fromCode,
                    $toCode,
                    $start + $index === $start,
                    $start + $index === $end,
                );
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $stop
     * @return array<string, mixed>
     */
    private function markSegmentEndpoints(
        array $stop,
        string $fromCode,
        string $toCode,
        bool $isFirst,
        bool $isLast,
    ): array {
        $stop['is_boarding'] = $isFirst || ($stop['code'] ?? '') === $fromCode;
        $stop['is_alighting'] = $isLast || ($stop['code'] ?? '') === $toCode;

        if ($stop['is_boarding']) {
            $stop['arrival'] = null;
        }

        if ($stop['is_alighting']) {
            $stop['departure'] = null;
        }

        return $stop;
    }

    /**
     * @param  array<string, mixed>  $stop
     * @return array{0: ?string, 1: ?string}
     */
    private function reconcileStopTimes(
        array $stop,
        ?string $arrival,
        ?string $departure,
        ?int $haltMinutes,
    ): array {
        $sta = $this->formatStopTime($stop['sta'] ?? null);
        $std = $this->formatStopTime($stop['std'] ?? null);

        if ($sta !== null && $std !== null) {
            return [$sta, $std];
        }

        if ($arrival !== null && $departure !== null && $arrival !== $departure) {
            return [$arrival, $departure];
        }

        if ($arrival !== null && $departure === null) {
            return [$arrival, $this->addMinutesToTime($arrival, $haltMinutes)];
        }

        if ($departure !== null && $arrival === null) {
            return [$this->subtractMinutesFromTime($departure, $haltMinutes), $departure];
        }

        if ($arrival !== null && $departure !== null && $arrival === $departure && $haltMinutes !== null && $haltMinutes > 0) {
            return [$arrival, $this->addMinutesToTime($arrival, $haltMinutes)];
        }

        return [$arrival, $departure];
    }

    /**
     * @param  array<string, mixed>  $stop
     */
    private function extractStopTime(array $stop, string $kind): ?string
    {
        $candidates = $kind === 'arrival'
            ? [
                $stop['scheduledArrival'] ?? null,
                $stop['actualArrival'] ?? null,
                $this->nestedTimeValue($stop['arrival'] ?? null),
                $stop['sta'] ?? null,
                $stop['arrivalTime'] ?? null,
                $stop['arrTime'] ?? null,
            ]
            : [
                $stop['scheduledDeparture'] ?? null,
                $stop['actualDeparture'] ?? null,
                $this->nestedTimeValue($stop['departure'] ?? null),
                $stop['std'] ?? null,
                $stop['departureTime'] ?? null,
                $stop['depTime'] ?? null,
            ];

        foreach ($candidates as $candidate) {
            $formatted = $this->formatStopTime($candidate);

            if ($formatted !== null) {
                return $formatted;
            }
        }

        return null;
    }

    private function nestedTimeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return $value['scheduled']
            ?? $value['actual']
            ?? $value['time']
            ?? null;
    }

    private function addMinutesToTime(?string $time, ?int $minutes): ?string
    {
        if ($time === null || $minutes === null || $minutes <= 0) {
            return null;
        }

        if (! preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $totalMinutes = ((int) $matches[1] * 60) + (int) $matches[2] + $minutes;

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60) % 24, $totalMinutes % 60);
    }

    private function subtractMinutesFromTime(?string $time, ?int $minutes): ?string
    {
        if ($time === null || $minutes === null || $minutes <= 0) {
            return null;
        }

        if (! preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $totalMinutes = max(0, ((int) $matches[1] * 60) + (int) $matches[2] - $minutes);

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60) % 24, $totalMinutes % 60);
    }

    private function formatStopTime(mixed $value): ?string
    {
        if (is_numeric($value)) {
            $minutes = (int) $value;

            if ($minutes >= 0 && $minutes < 24 * 60) {
                return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            }
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || in_array(strtoupper($value), ['SRC', '--', '----', 'NA', 'N/A'], true)) {
            return null;
        }

        if (preg_match('/T(\d{2}:\d{2})/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(\d{2}:\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
