<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProjectStoreControllerTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Campus food delivery app',
            'description' => 'A small app so students can order from the cafeteria.',
            'skills_needed' => ['React Native', ' Laravel ', 'react native'],
            'tags' => ['tech'],
            'team_size' => 4,
            ...$overrides,
        ];
    }

    public function test_returns_403_for_an_unverified_student(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->postJson(route('api.v1.projects.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_returns_201_and_the_owner_is_on_the_team(): void
    {
        $user = User::factory()->create();

        $this->asToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.projects.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.skills_needed', ['React Native', 'Laravel'])
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.my_status', 'accepted')
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.members.0.id', $user->id);
    }

    public function test_returns_422_for_invalid_values(): void
    {
        $this->asToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.projects.store'), $this->payload([
                'title' => '',
                'description' => '',
                'skills_needed' => [],
                'tags' => ['robots'],
                'team_size' => 11,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'skills_needed', 'tags.0', 'team_size']);

        $this->assertSame(0, Project::count());
    }
}
