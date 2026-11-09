<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $latest = $this->latestMessage;

        return [
            'id' => $this->id,
            'other' => new StudentResource($this->otherParticipant($user)),
            'last_message' => $latest ? [
                'preview' => Str::limit($latest->body, 80),
                'is_mine' => $latest->sender_id === $user->id,
                'created_at' => $latest->created_at->toIso8601String(),
            ] : null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'blocked' => (bool) ($this->blocked ?? false),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
