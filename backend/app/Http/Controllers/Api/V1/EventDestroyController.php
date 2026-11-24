<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Notifications\EventCancelledNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class EventDestroyController extends Controller
{
    /**
     * Cancel an event (creator only). Everyone else who was going is told.
     */
    public function __invoke(Event $event): Response
    {
        Gate::authorize('delete', $event);

        $attendees = $event->attendees()->whereKeyNot($event->creator_id)->get();
        $notification = new EventCancelledNotification($event->title, $event->starts_at);

        $event->delete();

        Notification::send($attendees, $notification);

        return response()->noContent();
    }
}
