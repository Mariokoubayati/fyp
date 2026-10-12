<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UserTrustControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.users.trust', User::factory()->create()))->assertUnauthorized();
    }

    public function test_returns_only_score_tier_and_max_loan_for_another_student(): void
    {
        $other = User::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.users.trust', $other))
            ->assertOk()
            ->assertExactJson(['data' => ['score' => 525, 'tier' => 'basic', 'max_loan' => 20]]);
    }

    public function test_returns_404_when_the_student_does_not_exist(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.users.trust', 9999))
            ->assertNotFound();
    }
}
