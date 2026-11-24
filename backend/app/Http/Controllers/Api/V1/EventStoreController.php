<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class EventStoreController extends Controller
{
    /**
     * Post an event; the creator is going automatically.
     */
    public function __invoke(StoreEventRequest $request): JsonResponse
    {
        $user = $request->user();

        $event = new Event($request->validated());
        $event->creator()->associate($user);
        $event->save();
        $event->attendees()->attach($user);

        return (new EventResource($event->load(['course', 'creator'])->loadAttendance($user)))
            ->response()
            ->setStatusCode(201);
    }
}
