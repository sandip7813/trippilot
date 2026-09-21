<?php

namespace App\Models;

use App\Enums\ExpenseSheetStatus;
use Database\Factories\TripExpenseSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $trip_id
 * @property ExpenseSheetStatus $status
 * @property list<array{id: string, name: string, email: string, phone: string|null, user_id: int|null, archived: bool}>|null $participants
 * @property array{user_id: int, name: string}|null $created_by
 * @property array{user_id: int, name: string}|null $settled_by
 * @property Carbon|null $settled_at
 * @property array<string, mixed>|null $settlement_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TripExpenseSheet extends Model
{
    /** @use HasFactory<TripExpenseSheetFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'trip_expense_sheets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'status',
        'participants',
        'created_by',
        'settled_by',
        'settled_at',
        'settlement_snapshot',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExpenseSheetStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TripExpenseSheetFactory
    {
        return TripExpenseSheetFactory::new();
    }

    public function isSettled(): bool
    {
        return $this->status === ExpenseSheetStatus::Settled;
    }

    /**
     * @return list<array{id: string, name: string, email: string, phone: string|null, user_id: int|null, archived: bool}>
     */
    public function participantList(): array
    {
        return array_values($this->getAttribute('participants') ?? []);
    }

    /**
     * @return array{id: string, name: string, email: string, phone: string|null, user_id: int|null, archived: bool}|null
     */
    public function findParticipant(string $participantId): ?array
    {
        return collect($this->participantList())->firstWhere('id', $participantId);
    }
}
