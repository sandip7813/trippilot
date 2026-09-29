<?php

namespace App\Models;

use Database\Factories\TripInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * A single contact thread between one sender and a trip's owner. Kept
 * in-platform: neither party's email or phone ever appears in a message or
 * notification, only a link back to the portal.
 *
 * @property string $id
 * @property string $trip_id
 * @property int $sender_id
 * @property string $subject
 * @property list<array{from_user_id: int, body: string, created_at: string}> $messages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TripInquiry extends Model
{
    /** @use HasFactory<TripInquiryFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'trip_inquiries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'sender_id',
        'subject',
        'messages',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1],
        ['sender_id' => 1],
    ];

    protected function casts(): array
    {
        return [
            'sender_id' => 'integer',
        ];
    }

    protected static function newFactory(): TripInquiryFactory
    {
        return TripInquiryFactory::new();
    }

    /**
     * @return list<array{from_user_id: int, body: string, created_at: string}>
     */
    public function messageList(): array
    {
        return array_values($this->getAttribute('messages') ?? []);
    }

    public function involves(User $user): bool
    {
        return (int) $this->sender_id === $user->id;
    }
}
