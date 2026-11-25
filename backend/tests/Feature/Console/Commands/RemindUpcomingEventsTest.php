<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindUpcomingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminds_everyone_going_to_an_event_within_a_day_only_once(): void
    {
        $soon = Event::factory()->create(['starts_at' => now()->addHours(20)]);
        $student = User::factory()->create();
        $soon->attendees()->attach($student);
        Event::factory()->create(['starts_at' => now()->addDays(3)])->attendees()->attach($student);

        $this->artisan('events:remind')->expectsOutputToContain('Sent 2 event reminder(s).')->assertSuccessful();
        $this->artisan('events:remind')->expectsOutputToContain('Sent 0 event reminder(s).')->assertSuccessful();

        $reminders = $student->notifications()->where('type', 'event_starting_soon')->get();
        $this->assertCount(1, $reminders);
        $this->assertSame($soon->id, $reminders->first()->data['event_id']);
        $this->assertSame(1, $soon->creator->notifications()->where('type', 'event_starting_soon')->count());
    }

    public function test_a_rescheduled_event_is_reminded_again_for_its_new_time(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addHours(5)]);
        $student = User::factory()->create();
        $event->attendees()->attach($student);

        $this->artisan('events:remind')->assertSuccessful();
        $event->update(['starts_at' => now()->addHours(10)]);
        $this->artisan('events:remind')->assertSuccessful();
        $this->artisan('events:remind')->expectsOutputToContain('Sent 0 event reminder(s).')->assertSuccessful();

        $this->assertSame(2, $student->notifications()->where('type', 'event_starting_soon')->count());
    }
}
