<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;

class EventUpdateController extends Controller
{
    /**
     * Edit an event (creator only).
     */
    public function __invoke(UpdateEventRequest $request, Event $event): EventResource
    {
        Gate::authorize('update', $event);

        $event->update($request->event());

        return new EventResource($event->load(['course', 'creator'])->loadAttendance($request->user()));
    }
}
