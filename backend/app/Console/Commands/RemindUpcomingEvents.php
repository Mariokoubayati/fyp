<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventStartingSoonNotification;
use Illuminate\Console\Command;

class RemindUpcomingEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'events:remind';

    /**
     * @var string
     */
    protected $description = 'Remind everyone going to an event that starts within the next 24 hours (once per event and start time, so a rescheduled event is reminded again)';

    public function handle(): int
    {
        $reminded = 0;

        Event::query()
            ->with('attendees')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDay())
            ->each(function (Event $event) use (&$reminded): void {
                $event->attendees->each(function (User $attendee) use ($event, &$reminded): void {
                    $alreadyReminded = $attendee->notifications()
                        ->where('type', 'event_starting_soon')
                        ->where('data->event_id', $event->id)
                        ->where('data->starts_at', $event->starts_at->toIso8601String())
                        ->exists();

                    if (! $alreadyReminded) {
                        $attendee->notify(new EventStartingSoonNotification($event));
                        $reminded++;
                    }
                });
            });

        $this->info("Sent {$reminded} event reminder(s).");

        return self::SUCCESS;
    }
}
