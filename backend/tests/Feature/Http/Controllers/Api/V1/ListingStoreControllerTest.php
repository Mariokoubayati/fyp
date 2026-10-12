<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ListingStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Database System Concepts, 7th ed.',
            'description' => 'Some highlighting in chapters 1-4.',
            'price' => 25.5,
            'category' => 'books',
            'condition' => 'good',
        ];
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.listings.store'), $this->payload())->assertUnauthorized();
    }

    public function test_returns_403_when_the_email_is_not_verified(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->unverified()->create()))
            ->postJson(route('api.v1.listings.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_returns_201_and_the_student_becomes_the_seller(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.listings.store'), [...$this->payload(), 'course_id' => $course->id])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Database System Concepts, 7th ed.')
            ->assertJsonPath('data.price', 25.5)
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.is_seller', true)
            ->assertJsonPath('data.seller.id', $user->id)
            ->assertJsonPath('data.course.id', $course->id);

        $this->assertDatabaseHas('listings', ['seller_id' => $user->id, 'category' => 'books', 'condition' => 'good']);
    }

    public function test_returns_422_when_the_payload_is_invalid(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(route('api.v1.listings.store'), [
                'title' => '',
                'price' => -5,
                'category' => 'cars',
                'condition' => 'broken',
                'course_id' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'price', 'category', 'condition', 'course_id']);
    }

    public function test_client_cannot_choose_the_seller_or_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->postJson(route('api.v1.listings.store'), [...$this->payload(), 'seller_id' => $other->id, 'status' => 'sold'])
            ->assertCreated()
            ->assertJsonPath('data.seller.id', $user->id)
            ->assertJsonPath('data.status', 'available');
    }
}
