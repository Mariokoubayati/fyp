<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventRsvpControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Send the next request as the token's student, as a fresh request would.
     */
    private function asToken(string $token): static
    {
        $this->forgetAuthenticatedRequest();

        return $this->withToken($token);
    }

    public function test_returns_200_and_the_student_is_going(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $this->asToken(JWTAuth::fromUser($user))
            ->putJson(route('api.v1.events.rsvp', $event))
            ->assertOk()
            ->assertJsonPath('data.is_going', true)
            ->assertJsonPath('data.attendees_count', 2);
    }

    public function test_saying_yes_twice_does_not_count_twice(): void
    {
        $event = Event::factory()->create();
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->asToken($token)->putJson(route('api.v1.events.rsvp', $event))->assertOk();
        $this->asToken($token)->putJson(route('api.v1.events.rsvp', $event))->assertOk();

        $this->assertSame(2, $event->attendees()->count());
    }

    public function test_returns_409_when_the_event_is_full_or_started(): void
    {
        $full = Event::factory()->create(['capacity' => 2]);
        $full->attendees()->attach(User::factory()->create());
        $started = Event::factory()->past()->create();
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->asToken($token)->putJson(route('api.v1.events.rsvp', $full))
            ->assertConflict()->assertJsonPath('message', 'This event is full.');
        $this->asToken($token)->putJson(route('api.v1.events.rsvp', $started))
            ->assertConflict()->assertJsonPath('message', 'This event has already started.');
    }

    public function test_events_without_a_capacity_never_fill_up(): void
    {
        $event = Event::factory()->create(['capacity' => null]);
        $event->attendees()->attach(User::factory()->count(30)->create());

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->putJson(route('api.v1.events.rsvp', $event))
            ->assertOk()
            ->assertJsonPath('data.attendees_count', 32);
    }

    public function test_returns_403_for_an_unverified_student(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->putJson(route('api.v1.events.rsvp', Event::factory()->create()))
            ->assertForbidden();
    }
}
