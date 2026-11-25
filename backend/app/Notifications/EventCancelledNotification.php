<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;

class EventCancelledNotification extends Notification
{
    /**
     * Holds plain values because the event row is deleted before the notification is stored.
     */
    public function __construct(public readonly string $title, public readonly CarbonInterface $startsAt) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'event_cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'starts_at' => $this->startsAt->toIso8601String(),
        ];
    }
}
