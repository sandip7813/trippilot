<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+3 months');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(2, 10).' days');

        return [
            'user_id' => User::factory(),
            'type' => TripType::Vacation,
            'title' => fake()->sentence(3),
            'destination' => [
                'label' => fake()->city().', '.fake()->country(),
                'lat' => null,
                'lng' => null,
                'place_id' => null,
            ],
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'budget' => fake()->randomFloat(2, 500, 5000),
            'travelers' => fake()->numberBetween(1, 6),
            'status' => TripStatus::Draft,
            'is_favorite' => false,
            'notes' => fake()->optional()->sentence(),
            'itinerary' => [
                'days' => [],
                'summary' => '',
                'packing_list' => [],
                'budget_breakdown' => [],
            ],
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (): array => [
            'status' => TripStatus::Planned,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => TripStatus::Archived,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (): array => [
            'is_favorite' => true,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function withItinerary(): static
    {
        return $this->state(fn (): array => [
            'status' => TripStatus::Planned,
            'itinerary' => [
                'days' => [
                    [
                        'day' => 1,
                        'title' => 'Day one',
                        'activities' => [
                            ['time' => '09:00', 'title' => 'Explore', 'notes' => null],
                        ],
                    ],
                ],
                'summary' => 'Sample generated plan.',
                'packing_list' => ['Passport'],
                'budget_breakdown' => [],
            ],
        ]);
    }

    public function road(): static
    {
        return $this->state(fn (): array => [
            'type' => TripType::Road,
            'origin' => [
                'label' => fake()->city(),
                'lat' => null,
                'lng' => null,
                'place_id' => null,
            ],
        ]);
    }
}
