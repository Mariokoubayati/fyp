<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupLeaveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $group = StudyGroup::factory()->create();

        $this->deleteJson(route('api.v1.study-groups.leave', $group))->assertUnauthorized();
    }

    public function test_returns_204_and_removes_the_member(): void
    {
        $group = StudyGroup::factory()->create();
        $user = User::factory()->create();
        $group->members()->attach($user);

        $this->withToken(JWTAuth::fromUser($user))
            ->deleteJson(route('api.v1.study-groups.leave', $group))
            ->assertNoContent();

        $this->assertFalse($group->members()->whereKey($user->id)->exists());
    }

    public function test_returns_204_when_the_student_was_not_a_member(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.study-groups.leave', $group))
            ->assertNoContent();
    }

    public function test_returns_422_when_the_creator_tries_to_leave(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser($group->creator))
            ->deleteJson(route('api.v1.study-groups.leave', $group))
            ->assertUnprocessable();

        $this->assertTrue($group->members()->whereKey($group->creator_id)->exists());
    }
}
