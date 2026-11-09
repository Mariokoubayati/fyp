<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationReadController extends Controller
{
    /**
     * Mark the given notifications as read, or all of them when no ids are sent.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['uuid']]);

        $request->user()->unreadNotifications()
            ->when($validated['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
