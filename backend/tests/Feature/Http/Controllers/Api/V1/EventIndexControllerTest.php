<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EventIndexControllerTest extends TestCase
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

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.events.index'))->assertUnauthorized();
    }

    public function test_lists_upcoming_events_soonest_first(): void
    {
        $later = Event::factory()->create(['starts_at' => now()->addDays(5)]);
        $sooner = Event::factory()->create(['starts_at' => now()->addDay()]);
        Event::factory()->past()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.events.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_filters_by_going_mine_tag_and_search(): void
    {
        $user = User::factory()->create();
        $mine = Event::factory()->for($user, 'creator')->create(['title' => 'Chess night', 'tags' => ['social']]);
        $going = Event::factory()->create(['title' => 'Football match', 'tags' => ['sports']]);
        $going->attendees()->attach($user);
        Event::factory()->create(['title' => 'Career fair', 'tags' => ['career']]);
        $token = JWTAuth::fromUser($user);

        $this->asToken($token)->getJson(route('api.v1.events.index', ['filter' => 'going']))
            ->assertOk()->assertJsonCount(2, 'data');
        $this->asToken($token)->getJson(route('api.v1.events.index', ['filter' => 'mine']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->asToken($token)->getJson(route('api.v1.events.index', ['tag' => 'sports']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $going->id);
        $this->asToken($token)->getJson(route('api.v1.events.index', ['search' => 'fair']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Career fair');
    }

    public function test_returns_422_for_an_unknown_filter(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.events.index', ['filter' => 'everything']))
            ->assertUnprocessable();
    }

    public function test_returns_422_for_an_unknown_topic(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.events.index', ['tag' => 'nonsense']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag');
    }
}
