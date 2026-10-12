<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class EnrollmentIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.me.courses.index'))->assertUnauthorized();
    }

    public function test_returns_only_the_courses_of_the_signed_in_student(): void
    {
        $user = User::factory()->create();
        $mine = Course::factory()->create();
        $theirs = Course::factory()->create();
        $user->courses()->attach($mine);
        User::factory()->create()->courses()->attach($theirs);

        $this->withToken(JWTAuth::fromUser($user))
            ->getJson(route('api.v1.me.courses.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }
}
