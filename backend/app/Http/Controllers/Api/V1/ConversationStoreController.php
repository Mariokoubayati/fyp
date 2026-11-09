<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\User;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\JsonResponse;

class ConversationStoreController extends Controller
{
    /**
     * Open the conversation with another student (the same one is returned if it exists).
     */
    public function __invoke(StoreConversationRequest $request, MessagingService $messaging): JsonResponse
    {
        $conversation = $messaging->start($request->user(), User::findOrFail($request->integer('user_id')));
        $conversation->load(['userOne', 'userTwo', 'latestMessage']);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }
}
