<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectLeaveRequestControllerTest extends TestCase
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

    public function test_students_can_withdraw_a_request_or_leave_the_team(): void
    {
        $project = Project::factory()->create();
        $pending = User::factory()->create();
        $member = User::factory()->create();
        $project->people()->attach($pending, ['status' => 'pending']);
        $project->people()->attach($member, ['status' => 'accepted']);

        $this->asToken(JWTAuth::fromUser($pending))->deleteJson(route('api.v1.projects.request.destroy', $project))->assertNoContent();
        $this->asToken(JWTAuth::fromUser($member))->deleteJson(route('api.v1.projects.request.destroy', $project))->assertNoContent();

        $this->assertSame(1, $project->people()->count());
    }

    public function test_a_declined_request_stays_on_record(): void
    {
        $project = Project::factory()->create();
        $declined = User::factory()->create();
        $project->people()->attach($declined, ['status' => 'declined']);

        $this->asToken(JWTAuth::fromUser($declined))->deleteJson(route('api.v1.projects.request.destroy', $project))->assertNoContent();

        $this->assertTrue($project->people()->whereKey($declined->id)->exists());
    }

    public function test_the_owner_cannot_leave(): void
    {
        $project = Project::factory()->create();

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->deleteJson(route('api.v1.projects.request.destroy', $project))
            ->assertUnprocessable();
    }
}
