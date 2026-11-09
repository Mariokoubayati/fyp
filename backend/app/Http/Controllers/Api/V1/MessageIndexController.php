<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageIndexController extends Controller
{
    /**
     * The latest messages, or only those after ?after=<id> when the app polls for new ones.
     */
    public function __invoke(Request $request, Conversation $conversation, MessagingService $messaging): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $request->validate(['after' => ['nullable', 'integer', 'min:0']]);

        return MessageResource::collection($messaging->read($conversation, $request->user(), $request->integer('after') ?: null));
    }
}
