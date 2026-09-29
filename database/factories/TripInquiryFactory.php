<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripInquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripInquiry>
 */
class TripInquiryFactory extends Factory
{
    protected $model = TripInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sender = User::factory()->create();

        return [
            'trip_id' => (string) Trip::factory()->create()->id,
            'sender_id' => $sender->id,
            'subject' => fake()->sentence(4),
            'messages' => [[
                'from_user_id' => $sender->id,
                'body' => fake()->paragraph(),
                'created_at' => now()->toIso8601String(),
            ]],
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (): array => ['trip_id' => (string) $trip->id]);
    }

    public function fromSender(User $sender): static
    {
        return $this->state(fn (array $attributes): array => [
            'sender_id' => $sender->id,
            'messages' => collect($attributes['messages'] ?? [])
                ->map(fn (array $message): array => ['from_user_id' => $sender->id] + $message)
                ->all(),
        ]);
    }
}
