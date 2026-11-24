<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Only the student who created the event can edit it.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->is($event->creator);
    }

    /**
     * Only the student who created the event can cancel it.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->is($event->creator);
    }
}
