<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupJoinControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $group = StudyGroup::factory()->create();

        $this->putJson(route('api.v1.study-groups.join', $group))->assertUnauthorized();
    }

    public function test_returns_200_and_adds_the_student(): void
    {
        $group = StudyGroup::factory()->create(['capacity' => 4]);
        $user = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->putJson(route('api.v1.study-groups.join', $group))
            ->assertOk()
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.members_count', 2);

        $this->assertTrue($group->members()->whereKey($user->id)->exists());
    }

    public function test_joining_twice_does_not_add_a_duplicate_member(): void
    {
        $group = StudyGroup::factory()->create(['capacity' => 4]);
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)->putJson(route('api.v1.study-groups.join', $group))->assertOk();
        $this->withToken($token)->putJson(route('api.v1.study-groups.join', $group))->assertOk();

        $this->assertSame(2, $group->members()->count());
    }

    public function test_returns_409_when_the_group_is_full(): void
    {
        $group = StudyGroup::factory()->create(['capacity' => 2]);
        $group->members()->attach(User::factory()->create());
        $outsider = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($outsider))
            ->putJson(route('api.v1.study-groups.join', $group))
            ->assertConflict()
            ->assertJsonPath('message', 'This study group is full.');

        $this->assertFalse($group->members()->whereKey($outsider->id)->exists());
    }

    public function test_returns_409_when_the_group_has_already_started(): void
    {
        $group = StudyGroup::factory()->past()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->putJson(route('api.v1.study-groups.join', $group))
            ->assertConflict();
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $group = StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->putJson(route('api.v1.study-groups.join', $group))
            ->assertForbidden();
    }
}
