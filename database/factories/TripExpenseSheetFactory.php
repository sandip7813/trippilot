<?php

namespace Database\Factories;

use App\Enums\ExpenseSheetStatus;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TripExpenseSheet>
 */
class TripExpenseSheetFactory extends Factory
{
    protected $model = TripExpenseSheet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => (string) Trip::factory()->create()->id,
            'status' => ExpenseSheetStatus::Open,
            'participants' => [
                $this->participant('Asha', 'asha@example.com'),
                $this->participant('Bala', 'bala@example.com'),
            ],
            'created_by' => ['user_id' => 1, 'name' => 'Owner'],
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (): array => ['trip_id' => (string) $trip->id]);
    }

    public function settled(): static
    {
        return $this->state(fn (): array => [
            'status' => ExpenseSheetStatus::Settled,
            'settled_at' => now(),
            'settled_by' => ['user_id' => 1, 'name' => 'Owner'],
        ]);
    }

    /**
     * @return array{id: string, name: string, email: string, phone: string|null, user_id: int|null, archived: bool}
     */
    private function participant(string $name, string $email): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => $name,
            'email' => $email,
            'phone' => null,
            'user_id' => null,
            'archived' => false,
        ];
    }
}
