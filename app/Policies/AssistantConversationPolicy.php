<?php

namespace App\Policies;

use App\Models\AssistantConversation;
use App\Models\User;

class AssistantConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AssistantConversation $assistantConversation): bool
    {
        return $this->owns($user, $assistantConversation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AssistantConversation $assistantConversation): bool
    {
        return $this->owns($user, $assistantConversation);
    }

    public function delete(User $user, AssistantConversation $assistantConversation): bool
    {
        return $this->owns($user, $assistantConversation);
    }

    private function owns(User $user, AssistantConversation $assistantConversation): bool
    {
        return (int) $assistantConversation->user_id === $user->id;
    }
}
