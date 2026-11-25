<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectIndexControllerTest extends TestCase
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

    public function test_lists_open_projects_by_default(): void
    {
        $open = Project::factory()->create();
        Project::factory()->closed()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.projects.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id)
            ->assertJsonPath('data.0.my_status', null);
    }

    public function test_filters_mine_joined_tag_and_search(): void
    {
        $user = User::factory()->create();
        $mine = Project::factory()->for($user, 'owner')->closed()->create(['title' => 'Robot arm', 'tags' => ['tech']]);
        $joined = Project::factory()->create(['title' => 'Charity run', 'tags' => ['volunteering']]);
        $joined->people()->attach($user, ['status' => 'accepted']);
        Project::factory()->create(['title' => 'Photo club zine', 'tags' => ['arts']])->people()->attach($user, ['status' => 'pending']);
        $token = JWTAuth::fromUser($user);

        $this->asToken($token)->getJson(route('api.v1.projects.index', ['filter' => 'mine']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->asToken($token)->getJson(route('api.v1.projects.index', ['filter' => 'joined']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $joined->id);
        $this->asToken($token)->getJson(route('api.v1.projects.index', ['tag' => 'arts']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.my_status', 'pending');
        $this->asToken($token)->getJson(route('api.v1.projects.index', ['search' => 'charity']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $joined->id);
    }

    public function test_returns_422_for_an_unknown_topic_or_filter(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->asToken($token)->getJson(route('api.v1.projects.index', ['tag' => 'crypto']))->assertUnprocessable()->assertJsonValidationErrors('tag');
        $this->asToken($token)->getJson(route('api.v1.projects.index', ['filter' => 'everything']))->assertUnprocessable();
    }
}
