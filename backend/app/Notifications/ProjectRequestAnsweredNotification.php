<?php

namespace App\Notifications;

use App\Enums\ProjectMemberStatus;
use App\Models\Project;
use Illuminate\Notifications\Notification;

class ProjectRequestAnsweredNotification extends Notification
{
    public function __construct(public readonly Project $project, public readonly ProjectMemberStatus $status) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->status === ProjectMemberStatus::Accepted ? 'project_request_accepted' : 'project_request_declined';
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
