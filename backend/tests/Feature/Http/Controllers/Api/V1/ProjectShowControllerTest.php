<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectShowControllerTest extends TestCase
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

    public function test_only_the_owner_sees_pending_requests(): void
    {
        $project = Project::factory()->create();
        $applicant = User::factory()->create(['skills' => ['Figma']]);
        $project->people()->attach($applicant, ['status' => 'pending', 'message' => 'I can do the design.']);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->getJson(route('api.v1.projects.show', $project))
            ->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.message', 'I can do the design.')
            ->assertJsonPath('data.requests.0.skills', ['Figma'])
            ->assertJsonCount(1, 'data.members');

        $this->asToken(JWTAuth::fromUser($applicant))
            ->getJson(route('api.v1.projects.show', $project))
            ->assertOk()
            ->assertJsonPath('data.my_status', 'pending')
            ->assertJsonMissingPath('data.requests');
    }
}
