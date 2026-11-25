<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventUpdateControllerTest extends TestCase
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

    public function test_the_creator_can_edit_the_event(): void
    {
        $event = Event::factory()->create();

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->patchJson(route('api.v1.events.update', $event), ['title' => 'New title', 'tags' => ['arts']])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.tags', ['arts']);
    }

    public function test_returns_403_for_someone_else(): void
    {
        $event = Event::factory()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(route('api.v1.events.update', $event), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $event->fresh()->title);
    }

    public function test_capacity_cannot_drop_below_the_students_going(): void
    {
        $event = Event::factory()->create(['capacity' => 10]);
        $event->attendees()->attach(User::factory()->count(3)->create());

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->patchJson(route('api.v1.events.update', $event), ['capacity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');
    }

    public function test_the_end_must_stay_after_the_existing_start(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addDays(5)]);

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->patchJson(route('api.v1.events.update', $event), ['ends_at' => now()->addDays(4)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }
}
