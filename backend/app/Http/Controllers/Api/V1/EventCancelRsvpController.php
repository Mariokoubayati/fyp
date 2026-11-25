<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventCancelRsvpController extends Controller
{
    /**
     * No longer going (idempotent). The creator cancels the event instead.
     */
    public function __invoke(Request $request, Event $event): Response
    {
        $user = $request->user();

        abort_if($user->is($event->creator), 422, 'You created this event. Cancel it instead.');

        $event->attendees()->detach($user->id);

        return response()->noContent();
    }
}
