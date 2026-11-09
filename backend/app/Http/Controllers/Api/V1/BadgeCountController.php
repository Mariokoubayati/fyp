<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeCountController extends Controller
{
    /**
     * Unread counts for the app's header badges.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadMessages = Conversation::query()
            ->involving($user)
            ->join('messages', 'messages.conversation_id', '=', 'conversations.id')
            ->where('messages.sender_id', '!=', $user->id)
            ->whereNull('messages.read_at')
            ->count();

        return response()->json(['data' => [
            'messages' => $unreadMessages,
            'notifications' => $user->unreadNotifications()->where('type', '!=', 'message')->count(),
        ]]);
    }
}
