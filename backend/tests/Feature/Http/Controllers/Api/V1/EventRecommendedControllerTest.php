<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventRecommendedControllerTest extends TestCase
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

    public function test_without_the_model_interests_come_first_then_courses_then_the_soonest(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create(['interests' => ['tech']]);
        $user->courses()->attach($course);

        $soonest = Event::factory()->create(['tags' => ['sports'], 'starts_at' => now()->addDay()]);
        $forMyCourse = Event::factory()->create(['tags' => ['sports'], 'course_id' => $course->id, 'starts_at' => now()->addDays(4)]);
        $matchesInterest = Event::factory()->create(['tags' => ['tech'], 'starts_at' => now()->addDays(9)]);

        $this->asToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.events.recommended'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $matchesInterest->id)
            ->assertJsonPath('data.1.id', $forMyCourse->id)
            ->assertJsonPath('data.2.id', $soonest->id);
    }

    public function test_leaves_out_events_that_are_mine_going_full_or_past(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user, 'creator')->create();
        Event::factory()->create()->attendees()->attach($user);
        Event::factory()->create(['capacity' => 2])->attendees()->attach(User::factory()->create());
        Event::factory()->past()->create();
        $open = Event::factory()->create();

        $this->asToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.events.recommended'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);
    }
}
