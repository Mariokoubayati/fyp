<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectJoinRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectJoinRequestControllerTest extends TestCase
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

    public function test_asking_to_join_creates_one_pending_request_and_tells_the_owner_once(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $student = User::factory()->create();
        $token = JWTAuth::fromUser($student);

        $this->asToken($token)
            ->putJson(route('api.v1.projects.request', $project), ['message' => 'I know Laravel.'])
            ->assertOk()
            ->assertJsonPath('data.my_status', 'pending')
            ->assertJsonPath('data.members_count', 1);
        $this->asToken($token)->putJson(route('api.v1.projects.request', $project))->assertOk();

        $this->assertSame(1, $project->requests()->count());
        Notification::assertSentToTimes($project->owner, ProjectJoinRequestedNotification::class, 1);
    }

    public function test_refuses_the_owner_closed_or_full_projects_and_declined_students(): void
    {
        $open = Project::factory()->create();
        $closed = Project::factory()->closed()->create();
        $full = Project::factory()->create(['team_size' => 2]);
        $full->people()->attach(User::factory()->create(), ['status' => 'accepted']);
        $student = User::factory()->create();
        $open->people()->attach($student, ['status' => 'declined']);
        $token = JWTAuth::fromUser($student);

        $this->asToken(JWTAuth::fromUser($open->owner))->putJson(route('api.v1.projects.request', $open))->assertUnprocessable();
        $this->asToken($token)->putJson(route('api.v1.projects.request', $closed))
            ->assertConflict()->assertJsonPath('message', 'This project is not looking for members.');
        $this->asToken($token)->putJson(route('api.v1.projects.request', $full))
            ->assertConflict()->assertJsonPath('message', 'This team is full.');
        $this->asToken($token)->putJson(route('api.v1.projects.request', $open))
            ->assertConflict()->assertJsonPath('message', 'The owner already declined your request.');
    }

    public function test_returns_403_for_an_unverified_student(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->putJson(route('api.v1.projects.request', Project::factory()->create()))
            ->assertForbidden();
    }
}
