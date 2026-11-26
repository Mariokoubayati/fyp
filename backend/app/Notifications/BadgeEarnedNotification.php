<?php

namespace App\Notifications;

use App\Enums\Badge;
use Illuminate\Notifications\Notification;

class BadgeEarnedNotification extends Notification
{
    public function __construct(public readonly Badge $badge) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'badge_earned';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'badge' => $this->badge->value,
            'title' => $this->badge->title(),
            'description' => $this->badge->description(),
        ];
    }
}
