<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectUpdateControllerTest extends TestCase
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

    public function test_the_owner_can_edit_and_close_the_project(): void
    {
        $project = Project::factory()->create();

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->patchJson(route('api.v1.projects.update', $project), ['title' => 'Renamed', 'is_open' => false])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.is_open', false);
    }

    public function test_returns_403_for_someone_else(): void
    {
        $project = Project::factory()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(route('api.v1.projects.update', $project), ['is_open' => false])
            ->assertForbidden();

        $this->assertTrue($project->fresh()->is_open);
    }

    public function test_team_size_cannot_drop_below_the_current_team(): void
    {
        $project = Project::factory()->create(['team_size' => 5]);
        $project->people()->attach(User::factory()->count(2)->create(), ['status' => 'accepted']);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->patchJson(route('api.v1.projects.update', $project), ['team_size' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_size');
    }
}
