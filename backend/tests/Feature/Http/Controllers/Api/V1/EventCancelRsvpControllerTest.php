<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventCancelRsvpControllerTest extends TestCase
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

    public function test_a_student_can_stop_going(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();
        $event->attendees()->attach($user);

        $this->asToken(JWTAuth::fromUser($user))
            ->deleteJson(route('api.v1.events.rsvp.destroy', $event))
            ->assertNoContent();

        $this->assertFalse($event->attendees()->whereKey($user->id)->exists());
    }

    public function test_the_creator_must_cancel_instead(): void
    {
        $event = Event::factory()->create();

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->deleteJson(route('api.v1.events.rsvp.destroy', $event))
            ->assertUnprocessable();

        $this->assertTrue($event->attendees()->whereKey($event->creator_id)->exists());
    }
}
