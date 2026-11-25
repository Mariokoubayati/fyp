<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Notifications\Notification;

class ProjectTeamFullNotification extends Notification
{
    public function __construct(public readonly Project $project) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'project_team_full';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'title' => $this->project->title,
            'other' => ['id' => $this->project->owner->id, 'name' => $this->project->owner->name],
        ];
    }
}
