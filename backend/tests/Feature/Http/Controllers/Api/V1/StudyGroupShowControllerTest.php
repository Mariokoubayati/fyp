<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupShowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $group = StudyGroup::factory()->create();

        $this->getJson(route('api.v1.study-groups.show', $group))->assertUnauthorized();
    }

    public function test_returns_200_with_course_and_creator(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.show', $group))
            ->assertOk()
            ->assertJsonPath('data.id', $group->id)
            ->assertJsonPath('data.course.code', $group->course->code)
            ->assertJsonPath('data.creator.id', $group->creator_id)
            ->assertJsonPath('data.is_member', false)
            ->assertJsonPath('data.is_creator', false);
    }

    public function test_returns_404_when_the_group_does_not_exist(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.show', 9999))
            ->assertNotFound();
    }
}
