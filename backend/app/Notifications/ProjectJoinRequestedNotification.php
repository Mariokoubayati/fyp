<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ProjectJoinRequestedNotification extends Notification
{
    public function __construct(public readonly Project $project, public readonly User $student) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'project_join_requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'title' => $this->project->title,
            'other' => ['id' => $this->student->id, 'name' => $this->student->name],
        ];
    }
}
