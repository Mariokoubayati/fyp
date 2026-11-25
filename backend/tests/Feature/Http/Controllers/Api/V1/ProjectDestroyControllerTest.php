<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectDestroyControllerTest extends TestCase
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

    public function test_only_the_owner_can_delete(): void
    {
        $project = Project::factory()->create();

        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.projects.destroy', $project))
            ->assertForbidden();
        $this->assertModelExists($project);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->deleteJson(route('api.v1.projects.destroy', $project))
            ->assertNoContent();
        $this->assertModelMissing($project);
    }
}
