<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventDestroyControllerTest extends TestCase
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

    public function test_the_creator_cancels_the_event_and_everyone_going_is_told(): void
    {
        Notification::fake();
        $event = Event::factory()->create();
        $attendees = User::factory()->count(2)->create();
        $event->attendees()->attach($attendees);

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->deleteJson(route('api.v1.events.destroy', $event))
            ->assertNoContent();

        $this->assertModelMissing($event);
        Notification::assertSentTo($attendees, EventCancelledNotification::class);
        Notification::assertNotSentTo($event->creator, EventCancelledNotification::class);
    }

    public function test_returns_403_for_someone_else(): void
    {
        $event = Event::factory()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.events.destroy', $event))
            ->assertForbidden();

        $this->assertModelExists($event);
    }
}
