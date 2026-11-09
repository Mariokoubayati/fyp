<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MessageStoreController extends Controller
{
    public function __invoke(StoreMessageRequest $request, Conversation $conversation, MessagingService $messaging): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return (new MessageResource($messaging->send($conversation, $request->user(), $request->string('body'))))
            ->response()
            ->setStatusCode(201);
    }
}
