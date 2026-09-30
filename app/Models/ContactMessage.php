<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactTopic;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message sent through the public contact page, by a guest or a signed-in user.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property ContactTopic $topic
 * @property string $subject
 * @property string $message
 * @property ContactMessageStatus $status
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, ContactMessageReply> $replies
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'topic', 'subject', 'message', 'status', 'replied_at'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * How dates appear in contact emails, e.g. "30 Sep 2026, 4:02 PM UTC".
     */
    public const string EMAIL_DATE_FORMAT = 'j M Y, g:i A T';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ContactMessageReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->oldest();
    }

    /**
     * Short public reference shown to the sender, e.g. "TP-00042".
     */
    public function reference(): string
    {
        return 'TP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'status' => ContactMessageStatus::class,
            'replied_at' => 'datetime',
        ];
    }
}
