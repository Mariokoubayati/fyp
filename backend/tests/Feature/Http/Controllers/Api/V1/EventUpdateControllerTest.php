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

    public function test_moving_the_start_moves_the_end_so_the_event_keeps_its_length(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addDay()->startOfHour(), 'ends_at' => now()->addDay()->startOfHour()->addHours(2)]);
        $newStart = now()->addDays(3)->startOfHour();

        $this->asToken(JWTAuth::fromUser($event->creator))
            ->patchJson(route('api.v1.events.update', $event), ['starts_at' => $newStart->toIso8601String()])
            ->assertOk();

        $event->refresh();
        $this->assertTrue($event->starts_at->equalTo($newStart));
        $this->assertTrue($event->ends_at->equalTo($newStart->copy()->addHours(2)));
    }

    public function test_an_end_sent_with_the_new_start_is_kept_and_no_end_stays_no_end(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addDay()->startOfHour(), 'ends_at' => now()->addDay()->startOfHour()->addHour()]);
        $open = Event::factory()->create(['creator_id' => $event->creator_id, 'ends_at' => null]);
        $token = JWTAuth::fromUser($event->creator);

        $this->asToken($token)->patchJson(route('api.v1.events.update', $event), [
            'starts_at' => now()->addDays(2)->startOfHour()->toIso8601String(),
            'ends_at' => now()->addDays(2)->startOfHour()->addHours(4)->toIso8601String(),
        ])->assertOk();
        $this->asToken($token)->patchJson(route('api.v1.events.update', $open), ['starts_at' => now()->addDays(4)->toIso8601String()])->assertOk();

        $this->assertTrue($event->fresh()->ends_at->equalTo(now()->addDays(2)->startOfHour()->addHours(4)));
        $this->assertNull($open->fresh()->ends_at);
    }
}
