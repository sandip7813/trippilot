<?php

namespace App\Services\Assistant;

use App\Models\Trip;
use App\Models\User;
use App\Services\Knowledge\RagService;
use Illuminate\Support\Str;

class AssistantContextBuilder
{
    public function __construct(private RagService $ragService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, string $message): array
    {
        $context = [
            'user_name' => $user->name,
            'home_city' => $user->homeCityLocation(),
            'recent_trips' => $this->recentTripsSummary($user),
        ];

        $retrieval = $this->ragService->retrieve($message);

        $context['rag_context'] = $retrieval['context'];
        $context['rag_sources'] = $retrieval['sources'];

        return $context;
    }

    /**
     * @return list<array{title: string, destination: string|null, start_date: string|null, type: string}>
     */
    private function recentTripsSummary(User $user): array
    {
        if (! extension_loaded('mongodb')) {
            return [];
        }

        try {
            return Trip::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(3)
                ->get(['title', 'destination', 'start_date', 'type'])
                ->map(function (Trip $trip): array {
                    $destination = Trip::normalizeLocation($trip->getAttribute('destination'));

                    return [
                        'title' => (string) $trip->title,
                        'destination' => is_array($destination) ? ($destination['label'] ?? null) : null,
                        'start_date' => $trip->start_date?->toDateString(),
                        'type' => $trip->type->value,
                    ];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function titleFromMessage(string $message): string
    {
        $title = Str::of(trim($message))
            ->replaceMatches('/\s+/', ' ')
            ->limit(60, '')
            ->trim()
            ->toString();

        return $title !== '' ? $title : 'New conversation';
    }
}
