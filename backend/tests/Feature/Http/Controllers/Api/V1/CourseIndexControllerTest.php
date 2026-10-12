<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CourseIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.courses.index'))->assertUnauthorized();
    }

    public function test_returns_200_with_courses_ordered_by_code(): void
    {
        Course::factory()->create(['code' => 'CSC-301', 'name' => 'Database Systems']);
        Course::factory()->create(['code' => 'CSC-101', 'name' => 'Introduction to Programming']);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.courses.index'))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'CSC-101')
            ->assertJsonPath('data.1.code', 'CSC-301')
            ->assertJsonStructure(['data' => [['id', 'code', 'name']], 'links', 'meta']);
    }

    public function test_search_filters_by_code_or_name(): void
    {
        Course::factory()->create(['code' => 'CSC-301', 'name' => 'Database Systems']);
        Course::factory()->create(['code' => 'MTH-101', 'name' => 'Calculus I']);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.courses.index', ['search' => 'calc']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'MTH-101');
    }
}
