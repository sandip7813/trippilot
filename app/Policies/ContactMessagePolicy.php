<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    /**
     * Senders can read their own messages and the replies to them.
     */
    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $contactMessage->user_id === $user->id;
    }
}
