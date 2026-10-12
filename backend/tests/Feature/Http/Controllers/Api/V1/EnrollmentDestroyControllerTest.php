<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EnrollmentDestroyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $course = Course::factory()->create();

        $this->deleteJson(route('api.v1.me.courses.destroy', $course))->assertUnauthorized();
    }

    public function test_returns_204_and_removes_the_enrollment(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $this->withToken(JWTAuth::fromUser($user))
            ->deleteJson(route('api.v1.me.courses.destroy', $course))
            ->assertNoContent();

        $this->assertDatabaseMissing('course_user', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_leaves_other_students_enrolled(): void
    {
        $user = User::factory()->create();
        $classmate = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);
        $classmate->courses()->attach($course);

        $this->withToken(JWTAuth::fromUser($user))
            ->deleteJson(route('api.v1.me.courses.destroy', $course))
            ->assertNoContent();

        $this->assertDatabaseHas('course_user', ['user_id' => $classmate->id, 'course_id' => $course->id]);
    }

    public function test_returns_204_when_the_student_was_not_enrolled(): void
    {
        $course = Course::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.me.courses.destroy', $course))
            ->assertNoContent();
    }
}
