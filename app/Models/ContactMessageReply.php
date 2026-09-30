<?php

namespace App\Models;

use Database\Factories\ContactMessageReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reply a super admin emailed back to a contact message's sender.
 *
 * @property int $id
 * @property int $contact_message_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read ContactMessage $contactMessage
 */
#[Fillable(['contact_message_id', 'user_id', 'body'])]
class ContactMessageReply extends Model
{
    /** @use HasFactory<ContactMessageReplyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ContactMessage, $this>
     */
    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    /**
     * The super admin who sent the reply.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
