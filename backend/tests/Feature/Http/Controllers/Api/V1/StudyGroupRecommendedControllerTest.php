<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupRecommendedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.study-groups.recommended'))->assertUnauthorized();
    }

    public function test_recommends_only_open_upcoming_groups_for_the_students_courses(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $match = StudyGroup::factory()->create(['course_id' => $course->id, 'capacity' => 5]);
        StudyGroup::factory()->create();
        StudyGroup::factory()->past()->create(['course_id' => $course->id]);
        $full = StudyGroup::factory()->create(['course_id' => $course->id, 'capacity' => 2]);
        $full->members()->attach(User::factory()->create());
        $joined = StudyGroup::factory()->create(['course_id' => $course->id]);
        $joined->members()->attach($user);

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.study-groups.recommended'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_ranks_groups_with_more_classmates_first(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $quiet = StudyGroup::factory()->create(['course_id' => $course->id, 'capacity' => 6, 'starts_at' => now()->addDay()]);
        $popular = StudyGroup::factory()->create(['course_id' => $course->id, 'capacity' => 6, 'starts_at' => now()->addDays(3)]);

        $classmates = User::factory()->count(2)->create();
        foreach ($classmates as $classmate) {
            $classmate->courses()->attach($course);
            $popular->members()->attach($classmate);
        }

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.study-groups.recommended'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $popular->id)
            ->assertJsonPath('data.1.id', $quiet->id);
    }

    public function test_returns_an_empty_list_when_the_student_has_no_courses(): void
    {
        StudyGroup::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.study-groups.recommended'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
