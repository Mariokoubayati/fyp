<?php

namespace App\Services\Messaging;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    /**
     * Open (or reopen) the conversation between two students.
     *
     * @throws ValidationException when blocked or restricted
     */
    public function start(User $from, User $to): Conversation
    {
        if (Block::betweenEither($from, $to)) {
            throw ValidationException::withMessages(['user_id' => 'You cannot message this student.']);
        }

        $existing = Conversation::query()
            ->where('user_one_id', min($from->id, $to->id))
            ->where('user_two_id', max($from->id, $to->id))
            ->first();

        if ($existing === null && $from->isChatRestricted()) {
            throw ValidationException::withMessages(['user_id' => 'Your account cannot start new conversations while reports about it are reviewed.']);
        }

        return $existing ?? Conversation::between($from, $to);
    }

    /**
     * @throws ValidationException when either student blocked the other
     */
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $recipient = $conversation->otherParticipant($sender);

        if (Block::betweenEither($sender, $recipient)) {
            throw ValidationException::withMessages(['body' => 'You cannot message this student.']);
        }

        $message = DB::transaction(function () use ($conversation, $sender, $body): Message {
            $message = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        $this->notifyOnce($recipient, $message);

        return $message->setRelation('sender', $sender);
    }

    /**
     * Messages in a conversation, oldest first: the latest page, or only those after a given id
     * (for polling). Marks what the reader received as read.
     *
     * @return Collection<int, Message>
     */
    public function read(Conversation $conversation, User $reader, ?int $afterId = null, int $limit = 50): Collection
    {
        $messages = $conversation->messages()
            ->when($afterId, fn (Builder $q) => $q->where('id', '>', $afterId)->orderBy('id')->limit($limit))
            ->unless($afterId, fn (Builder $q) => $q->orderByDesc('id')->limit($limit))
            ->get()
            ->sortBy('id')
            ->values();

        $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $reader->unreadNotifications()
            ->where('type', 'message')
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);

        return $messages;
    }

    /**
     * One unread notification per conversation is enough; more messages do not stack up.
     */
    private function notifyOnce(User $recipient, Message $message): void
    {
        $alreadyNotified = $recipient->unreadNotifications()
            ->where('type', 'message')
            ->where('data->conversation_id', $message->conversation_id)
            ->exists();

        if (! $alreadyNotified) {
            $recipient->notify(new NewMessageNotification($message));
        }
    }
}
