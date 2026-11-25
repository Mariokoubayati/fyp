<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectRequestAnsweredNotification;
use App\Notifications\ProjectTeamFullNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectMemberDecisionControllerTest extends TestCase
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

    public function test_the_owner_accepts_or_declines_and_the_student_is_told(): void
    {
        Notification::fake();
        $project = Project::factory()->create(['team_size' => 4]);
        [$yes, $no] = User::factory()->count(2)->create()->all();
        $project->people()->attach([$yes->id => ['status' => 'pending'], $no->id => ['status' => 'pending']]);
        $token = JWTAuth::fromUser($project->owner);

        $this->asToken($token)
            ->putJson(route('api.v1.projects.members.update', [$project, $yes]), ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonCount(1, 'data.requests');
        $this->asToken($token)
            ->putJson(route('api.v1.projects.members.update', [$project, $no]), ['status' => 'declined'])
            ->assertOk()
            ->assertJsonCount(0, 'data.requests');

        Notification::assertSentTo($yes, ProjectRequestAnsweredNotification::class, fn ($n) => $n->databaseType($yes) === 'project_request_accepted');
        Notification::assertSentTo($no, ProjectRequestAnsweredNotification::class, fn ($n) => $n->databaseType($no) === 'project_request_declined');
    }

    public function test_returns_409_when_accepting_into_a_full_team(): void
    {
        $project = Project::factory()->create(['team_size' => 2]);
        $project->people()->attach(User::factory()->create(), ['status' => 'accepted']);
        $waiting = User::factory()->create();
        $project->people()->attach($waiting, ['status' => 'pending']);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->putJson(route('api.v1.projects.members.update', [$project, $waiting]), ['status' => 'accepted'])
            ->assertConflict();

        $this->assertSame(2, $project->members()->count());
    }

    public function test_only_the_owner_decides_and_only_on_pending_requests(): void
    {
        $project = Project::factory()->create();
        $waiting = User::factory()->create();
        $project->people()->attach($waiting, ['status' => 'pending']);

        $this->asToken(JWTAuth::fromUser($waiting))
            ->putJson(route('api.v1.projects.members.update', [$project, $waiting]), ['status' => 'accepted'])
            ->assertForbidden();
        $this->asToken(JWTAuth::fromUser($project->owner))
            ->putJson(route('api.v1.projects.members.update', [$project, User::factory()->create()]), ['status' => 'accepted'])
            ->assertNotFound();
        $this->asToken(JWTAuth::fromUser($project->owner))
            ->putJson(route('api.v1.projects.members.update', [$project, $waiting]), ['status' => 'maybe'])
            ->assertUnprocessable();
    }

    public function test_filling_the_last_seat_clears_the_other_requests_and_tells_them(): void
    {
        Notification::fake();
        $project = Project::factory()->create(['team_size' => 2]);
        [$chosen, $late, $other] = User::factory()->count(3)->create()->all();
        $project->people()->attach([$chosen->id => ['status' => 'pending'], $late->id => ['status' => 'pending'], $other->id => ['status' => 'pending']]);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->putJson(route('api.v1.projects.members.update', [$project, $chosen]), ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.is_full', true)
            ->assertJsonCount(0, 'data.requests');

        $this->assertSame(0, $project->requests()->count());
        Notification::assertSentTo([$late, $other], ProjectTeamFullNotification::class);
        Notification::assertNotSentTo($chosen, ProjectTeamFullNotification::class);

        $project->update(['team_size' => 3]);
        $this->asToken(JWTAuth::fromUser($late))
            ->putJson(route('api.v1.projects.request', $project))
            ->assertOk()
            ->assertJsonPath('data.my_status', 'pending');
    }
}
