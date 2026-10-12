<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.study-groups.index'))->assertUnauthorized();
    }

    public function test_lists_upcoming_groups_soonest_first_and_hides_past_ones(): void
    {
        $later = StudyGroup::factory()->create(['starts_at' => now()->addDays(5)]);
        $sooner = StudyGroup::factory()->create(['starts_at' => now()->addDay()]);
        StudyGroup::factory()->past()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_filters_by_course(): void
    {
        $wanted = StudyGroup::factory()->create();
        StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.index', ['course_id' => $wanted->course_id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_mine_returns_only_groups_the_student_joined(): void
    {
        $user = User::factory()->create();
        $joined = StudyGroup::factory()->create();
        $joined->members()->attach($user);
        StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.study-groups.index', ['mine' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $joined->id)
            ->assertJsonPath('data.0.is_member', true);
    }

    public function test_includes_member_count_and_full_flag(): void
    {
        $group = StudyGroup::factory()->create(['capacity' => 2]);
        $group->members()->attach(User::factory()->create());

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.index'))
            ->assertOk()
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonPath('data.0.is_full', true)
            ->assertJsonPath('data.0.is_member', false);
    }
}
