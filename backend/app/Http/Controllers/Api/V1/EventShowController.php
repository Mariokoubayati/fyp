<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventShowController extends Controller
{
    /**
     * Show one event.
     */
    public function __invoke(Request $request, Event $event): EventResource
    {
        return new EventResource($event->load(['course', 'creator'])->loadAttendance($request->user()));
    }
}
