<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupDestroyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $group = StudyGroup::factory()->create();

        $this->deleteJson(route('api.v1.study-groups.destroy', $group))->assertUnauthorized();
    }

    public function test_returns_204_when_the_creator_deletes_the_group(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser($group->creator))
            ->deleteJson(route('api.v1.study-groups.destroy', $group))
            ->assertNoContent();

        $this->assertModelMissing($group);
        $this->assertDatabaseMissing('study_group_user', ['study_group_id' => $group->id]);
    }

    public function test_returns_403_when_someone_else_deletes_the_group(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.study-groups.destroy', $group))
            ->assertForbidden();

        $this->assertModelExists($group);
    }
}
