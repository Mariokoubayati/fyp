<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EnrollmentStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $course = Course::factory()->create();

        $this->putJson(route('api.v1.me.courses.store', $course))->assertUnauthorized();
    }

    public function test_returns_204_and_enrolls_the_student(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->putJson(route('api.v1.me.courses.store', $course))
            ->assertNoContent();

        $this->assertDatabaseHas('course_user', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_enrolling_twice_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)->putJson(route('api.v1.me.courses.store', $course))->assertNoContent();
        $this->withToken($token)->putJson(route('api.v1.me.courses.store', $course))->assertNoContent();

        $this->assertSame(1, $user->courses()->count());
    }

    public function test_returns_404_when_the_course_does_not_exist(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->putJson(route('api.v1.me.courses.store', 9999))
            ->assertNotFound();
    }
}
