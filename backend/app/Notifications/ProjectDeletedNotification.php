<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ProjectDeletedNotification extends Notification
{
    /**
     * Holds plain values because the project row is deleted before the notification is stored.
     *
     * @param  array{id: int, name: string}  $owner
     */
    public function __construct(public readonly string $title, public readonly array $owner) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'project_deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'other' => $this->owner,
        ];
    }
}
