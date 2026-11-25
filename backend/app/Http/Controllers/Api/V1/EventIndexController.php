<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampusTag;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventIndexController extends Controller
{
    /**
     * List upcoming events: all of them, the ones the student is going to, or the ones they created.
     * Optionally only one tag, or a title search.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,going,mine'],
            'tag' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $tag = CampusTag::tryFrom((string) $request->query('tag'));

        $events = Event::query()
            ->with(['course', 'creator'])
            ->withAttendance($user)
            ->upcoming()
            ->when($request->query('filter') === 'going', fn ($query) => $query->whereHas('attendees', fn ($attendees) => $attendees->whereKey($user->id)))
            ->when($request->query('filter') === 'mine', fn ($query) => $query->where('creator_id', $user->id))
            ->when($tag, fn ($query) => $query->whereJsonContains('tags', $tag->value))
            ->when($request->filled('search'), fn ($query) => $query->whereContains('title', (string) $request->query('search')))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return EventResource::collection($events);
    }
}
