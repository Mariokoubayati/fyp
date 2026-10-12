<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudyGroupStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Course $course): array
    {
        return [
            'course_id' => $course->id,
            'title' => 'Databases midterm prep',
            'description' => 'Normalisation and SQL joins.',
            'location' => 'Library, 2nd floor',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'capacity' => 5,
        ];
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.study-groups.store'), [])->assertUnauthorized();
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.study-groups.store'), $this->payload($course))
            ->assertForbidden();
    }

    public function test_returns_201_and_the_creator_joins_automatically(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.study-groups.store'), $this->payload($course))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Databases midterm prep')
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.is_creator', true)
            ->assertJsonPath('data.course.id', $course->id);

        $group = StudyGroup::firstOrFail();
        $this->assertSame($user->id, $group->creator_id);
        $this->assertTrue($group->members->contains($user));
    }

    public function test_returns_422_when_the_student_is_not_enrolled_in_the_course(): void
    {
        $course = Course::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.study-groups.store'), $this->payload($course))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['course_id']);
    }

    public function test_returns_422_when_the_payload_is_invalid(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $user->courses()->attach($course);

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.study-groups.store'), [
                ...$this->payload($course),
                'title' => '',
                'starts_at' => now()->subDay()->toIso8601String(),
                'capacity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'starts_at', 'capacity']);
    }
}
