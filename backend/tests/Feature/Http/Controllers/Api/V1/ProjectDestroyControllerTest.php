<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectDeletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_deleting_tells_members_and_waiting_students_but_not_the_owner_or_declined(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        [$member, $waiting, $declined] = User::factory()->count(3)->create()->all();
        $project->people()->attach([$member->id => ['status' => 'accepted'], $waiting->id => ['status' => 'pending'], $declined->id => ['status' => 'declined']]);

        $this->asToken(JWTAuth::fromUser($project->owner))
            ->deleteJson(route('api.v1.projects.destroy', $project))
            ->assertNoContent();

        Notification::assertSentTo([$member, $waiting], ProjectDeletedNotification::class, fn ($n) => $n->toArray($member)['title'] === $project->title);
        Notification::assertNotSentTo([$project->owner, $declined], ProjectDeletedNotification::class);
    }
}
