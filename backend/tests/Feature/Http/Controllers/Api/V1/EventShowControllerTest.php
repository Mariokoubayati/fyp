<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventShowControllerTest extends TestCase
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

    public function test_returns_the_event_with_attendance_for_the_viewer(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        $viewer = User::factory()->create();

        $this->asToken(JWTAuth::fromUser($viewer))
            ->getJson(route('api.v1.events.show', $event))
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.attendees_count', 1)
            ->assertJsonPath('data.is_going', false)
            ->assertJsonPath('data.is_creator', false)
            ->assertJsonPath('data.creator.id', $event->creator_id);
    }

    public function test_returns_404_for_an_unknown_event(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.events.show', 999))
            ->assertNotFound();
    }

    public function test_a_deleted_event_gets_a_plain_message(): void
    {
        $event = Event::factory()->create();
        $event->delete();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.events.show', $event->id))
            ->assertNotFound()
            ->assertExactJson(['message' => 'This was removed or no longer exists.']);
    }
}
