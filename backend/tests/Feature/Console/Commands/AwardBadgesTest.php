<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\BadgeEarnedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AwardBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_goer_is_awarded_once_the_third_event_has_started(): void
    {
        Notification::fake();
        $student = User::factory()->create();
        Event::factory()->past()->count(2)->create()->each(fn (Event $event) => $event->attendees()->attach($student));
        $upcoming = Event::factory()->create(['starts_at' => now()->addHours(2)]);
        $upcoming->attendees()->attach($student);

        $this->artisan('badges:award')->expectsOutputToContain('Awarded 0 badge(s).')->assertSuccessful();

        $this->travel(3)->hours();
        $this->artisan('badges:award')->expectsOutputToContain('Awarded 1 badge(s).')->assertSuccessful();
        $this->artisan('badges:award')->expectsOutputToContain('Awarded 0 badge(s).')->assertSuccessful();

        Notification::assertSentToTimes($student, BadgeEarnedNotification::class, 1);
    }
}
