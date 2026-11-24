<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventStoreControllerTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Hackathon kickoff',
            'location' => 'Main auditorium',
            'starts_at' => now()->addDays(3)->setTime(18, 0)->toIso8601String(),
            'ends_at' => now()->addDays(3)->setTime(21, 0)->toIso8601String(),
            'capacity' => 40,
            'tags' => ['tech', 'career'],
            ...$overrides,
        ];
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.events.store'), $this->payload())->assertUnauthorized();
    }

    public function test_returns_403_for_an_unverified_student(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->postJson(route('api.v1.events.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Event::count());
    }

    public function test_returns_201_and_the_creator_is_going(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $this->asToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.events.store'), $this->payload(['course_id' => $course->id]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hackathon kickoff')
            ->assertJsonPath('data.tags', ['tech', 'career'])
            ->assertJsonPath('data.attendees_count', 1)
            ->assertJsonPath('data.is_going', true)
            ->assertJsonPath('data.is_creator', true)
            ->assertJsonPath('data.course.id', $course->id);

        $this->assertTrue(Event::first()->attendees()->whereKey($user->id)->exists());
    }

    public function test_capacity_and_end_time_are_optional(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.events.store'), $this->payload(['capacity' => null, 'ends_at' => null]))
            ->assertCreated()
            ->assertJsonPath('data.capacity', null)
            ->assertJsonPath('data.is_full', false);
    }

    public function test_returns_422_for_invalid_values(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->asToken($token)
            ->postJson(route('api.v1.events.store'), $this->payload([
                'title' => '',
                'location' => '',
                'starts_at' => now()->subHour()->toIso8601String(),
                'capacity' => 1,
                'tags' => [],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'location', 'starts_at', 'capacity', 'tags']);

        $this->asToken($token)
            ->postJson(route('api.v1.events.store'), $this->payload([
                'ends_at' => now()->addDays(2)->toIso8601String(),
                'tags' => ['tech', 'tech', 'parties'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at', 'tags.0', 'tags.2']);

        $this->asToken($token)
            ->postJson(route('api.v1.events.store'), $this->payload(['tags' => ['tech', 'arts', 'sports', 'social']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags']);
    }
}
