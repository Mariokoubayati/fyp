<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Notification;

class EventStartingSoonNotification extends Notification
{
    public function __construct(public readonly Event $event) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'event_starting_soon';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'starts_at' => $this->event->starts_at->toIso8601String(),
            'location' => $this->event->location,
        ];
    }
}
