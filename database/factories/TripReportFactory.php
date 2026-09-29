<?php

namespace Database\Factories;

use App\Enums\TripReportStatus;
use App\Models\Trip;
use App\Models\TripReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripReport>
 */
class TripReportFactory extends Factory
{
    protected $model = TripReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => (string) Trip::factory()->create()->id,
            'reporter_id' => User::factory()->create()->id,
            'reason' => fake()->randomElement(['spam', 'scam', 'inappropriate', 'other']),
            'message' => fake()->sentence(),
            'status' => TripReportStatus::Open,
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (): array => ['trip_id' => (string) $trip->id]);
    }
}
