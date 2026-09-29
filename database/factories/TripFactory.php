<?php

namespace Database\Factories;

use App\Enums\ExpenseSheetVisibility;
use App\Enums\OpenTripCostModel;
use App\Enums\TripCollaboratorRole;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Enums\TripVisibility;
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

    public function withCollaborator(User $user, TripCollaboratorRole $role = TripCollaboratorRole::Viewer): static
    {
        return $this->state(fn (array $attributes): array => [
            'collaborators' => [
                ...($attributes['collaborators'] ?? []),
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $role->value,
                    'status' => 'accepted',
                    'added_at' => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * A published open trip, with a sensible default group details block.
     */
    public function openTrip(): static
    {
        return $this->state(fn (): array => [
            'visibility' => TripVisibility::Public,
            'published_at' => now(),
            'open_trip' => [
                'category' => 'trek',
                'max_group_size' => 8,
                'join_deadline' => now()->addWeeks(2)->toIso8601String(),
                'difficulty' => 'moderate',
                'requirements' => 'Basic fitness, own trekking gear.',
                'meeting_point' => 'Base camp gate',
                'rules' => 'Respect the group schedule.',
                'cost_model' => OpenTripCostModel::CostSharing->value,
                'cost_amount' => 6000.0,
                'cost_currency' => 'INR',
                'cost_inclusions' => 'Guide, permits, shared camping.',
                'share_itinerary_with_members' => true,
                'member_names_visible' => false,
            ],
        ]);
    }

    public function expenseSheetShared(): static
    {
        return $this->state(fn (): array => [
            'expense_sheet_visibility' => ExpenseSheetVisibility::Shared,
        ]);
    }

    public function road(): static
    {
        return $this->state(fn (): array => [
            'type' => TripType::Road,
            'origin' => [
                'label' => 'Mumbai, India',
                'lat' => 19.076,
                'lng' => 72.8777,
                'place_id' => 'test-origin',
                'country_code' => 'in',
            ],
            'destination' => [
                'label' => 'Pune, India',
                'lat' => 18.5204,
                'lng' => 73.8567,
                'place_id' => 'test-destination',
                'country_code' => 'in',
            ],
            'road_profile' => [
                'vehicle_class' => 'car',
                'fuel_type' => 'petrol',
                'driving_pace' => 'standard',
                'avoid_tolls' => false,
                'avoid_highways' => false,
            ],
            'stops' => [],
            'suggested_breaks' => [],
            'route' => null,
        ]);
    }
}
