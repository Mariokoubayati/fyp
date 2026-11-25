<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectRecommendedControllerTest extends TestCase
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

    public function test_without_the_model_projects_needing_my_skills_come_first_then_my_interests(): void
    {
        $user = User::factory()->create(['skills' => ['Python'], 'interests' => ['arts']]);
        $newest = Project::factory()->create(['skills_needed' => ['Marketing'], 'tags' => ['career']]);
        $interest = Project::factory()->create(['skills_needed' => ['Marketing'], 'tags' => ['arts'], 'created_at' => now()->subDay()]);
        $skills = Project::factory()->create(['skills_needed' => ['python', 'SQL'], 'tags' => ['tech'], 'created_at' => now()->subDays(2)]);

        $this->asToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.projects.recommended'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $skills->id)
            ->assertJsonPath('data.1.id', $interest->id)
            ->assertJsonPath('data.2.id', $newest->id);
    }

    public function test_leaves_out_my_own_closed_full_and_already_requested_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user, 'owner')->create();
        Project::factory()->closed()->create();
        $full = Project::factory()->create(['team_size' => 2]);
        $full->people()->attach(User::factory()->create(), ['status' => 'accepted']);
        Project::factory()->create()->people()->attach($user, ['status' => 'declined']);
        $open = Project::factory()->create();

        $this->asToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.projects.recommended'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);
    }
}
