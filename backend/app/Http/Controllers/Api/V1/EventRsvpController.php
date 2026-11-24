<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventRsvpController extends Controller
{
    /**
     * Say you are going (idempotent). Fails with 409 when the event is full or has started.
     * Going to events does not change the trust score.
     */
    public function __invoke(Request $request, Event $event): EventResource
    {
        $user = $request->user();

        DB::transaction(function () use ($event, $user): void {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->attendees()->whereKey($user->id)->exists()) {
                return;
            }

            abort_if($locked->starts_at->isPast(), 409, 'This event has already started.');
            abort_if($locked->capacity !== null && $locked->attendees()->count() >= $locked->capacity, 409, 'This event is full.');

            $locked->attendees()->attach($user);
        });

        return new EventResource($event->load(['course', 'creator'])->loadAttendance($user));
    }
}
