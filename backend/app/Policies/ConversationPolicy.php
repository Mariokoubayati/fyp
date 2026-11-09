<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two participants can read or write in a conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }
}
