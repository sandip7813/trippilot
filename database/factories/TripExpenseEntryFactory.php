<?php

namespace Database\Factories;

use App\Enums\ExpenseEntryType;
use App\Enums\ExpenseSplitType;
use App\Models\TripExpenseEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripExpenseEntry>
 */
class TripExpenseEntryFactory extends Factory
{
    protected $model = TripExpenseEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => 'trip',
            'sheet_id' => 'sheet',
            'category' => 'other',
            'type' => ExpenseEntryType::Payment,
            'title' => fake()->words(2, true),
            'entry_date' => now()->toDateString(),
            'amount' => 100000,
            'payers' => [],
            'split_type' => ExpenseSplitType::Equal,
            'split_inputs' => [],
            'splits' => [],
            'created_by' => ['user_id' => 1, 'name' => 'Owner'],
        ];
    }
}
