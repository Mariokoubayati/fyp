<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ConversationIndexController extends Controller
{
    /**
     * The student's conversations, most recent first, with unread counts.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->involving($user)
            ->whereNotNull('last_message_at')
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_id', '!=', $user->id)->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->paginate(30);

        $blocked = DB::table('blocks')
            ->where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(DB::table('blocks')->where('blocked_id', $user->id)->pluck('blocker_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        $conversations->each(fn (Conversation $c) => $c->setAttribute('blocked', in_array($c->otherParticipantId($user), $blocked, true)));

        return ConversationResource::collection($conversations);
    }
}
