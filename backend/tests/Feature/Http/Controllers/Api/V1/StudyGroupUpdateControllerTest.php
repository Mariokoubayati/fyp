<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $group = StudyGroup::factory()->create();

        $this->patchJson(route('api.v1.study-groups.update', $group), ['title' => 'New'])->assertUnauthorized();
    }

    public function test_returns_200_when_the_creator_edits_the_group(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser($group->creator))
            ->patchJson(route('api.v1.study-groups.update', $group), ['title' => 'Renamed', 'capacity' => 10])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.capacity', 10);

        $this->assertDatabaseHas('study_groups', ['id' => $group->id, 'title' => 'Renamed']);
    }

    public function test_returns_403_when_someone_else_edits_the_group(): void
    {
        $group = StudyGroup::factory()->create(['title' => 'Original']);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(route('api.v1.study-groups.update', $group), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('study_groups', ['id' => $group->id, 'title' => 'Original']);
    }

    public function test_returns_422_when_capacity_is_below_the_current_member_count(): void
    {
        $group = StudyGroup::factory()->create(['capacity' => 6]);
        $group->members()->attach(User::factory()->count(3)->create());

        $this->withToken(JWTAuth::fromUser($group->creator))
            ->patchJson(route('api.v1.study-groups.update', $group), ['capacity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }
}
