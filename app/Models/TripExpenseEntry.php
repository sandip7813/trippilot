<?php

namespace App\Models;

use App\Enums\ExpenseEntryType;
use App\Enums\ExpenseSplitType;
use Database\Factories\TripExpenseEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

/**
 * Amounts are stored in minor units (paise). For a refund, `payers` are the people who
 * received the money back and `splits` are the people credited; for a transfer, `payers`
 * holds the sender and `splits` the receiver.
 *
 * @property string $id
 * @property string $trip_id
 * @property string $sheet_id
 * @property string|null $category
 * @property ExpenseEntryType $type
 * @property string $title
 * @property Carbon|null $entry_date
 * @property int $amount
 * @property list<array{participant_id: string, amount: int}> $payers
 * @property ExpenseSplitType $split_type
 * @property list<array{participant_id: string, value: float|int|null}> $split_inputs
 * @property list<array{participant_id: string, amount: int}> $splits
 * @property string|null $notes
 * @property array{user_id: int, name: string} $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class TripExpenseEntry extends Model
{
    /** @use HasFactory<TripExpenseEntryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $connection = 'mongodb';

    protected string $collection = 'trip_expense_entries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'sheet_id',
        'category',
        'type',
        'title',
        'entry_date',
        'amount',
        'payers',
        'split_type',
        'split_inputs',
        'splits',
        'notes',
        'created_by',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1],
        ['sheet_id' => 1],
        ['category' => 1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExpenseEntryType::class,
            'split_type' => ExpenseSplitType::class,
            'entry_date' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function newFactory(): TripExpenseEntryFactory
    {
        return TripExpenseEntryFactory::new();
    }
}
