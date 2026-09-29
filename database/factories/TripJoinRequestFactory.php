<?php

namespace Database\Factories;

use App\Enums\TripJoinRequestStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripJoinRequest>
 */
class TripJoinRequestFactory extends Factory
{
    protected $model = TripJoinRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => (string) Trip::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'travelers_count' => 1,
            'phone' => '9999999999',
            'message' => fake()->sentence(),
            'status' => TripJoinRequestStatus::Pending,
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (): array => ['trip_id' => (string) $trip->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => TripJoinRequestStatus::Accepted,
            'decided_at' => now(),
        ]);
    }
}
