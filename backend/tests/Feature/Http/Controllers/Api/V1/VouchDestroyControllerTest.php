<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Vouch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class VouchDestroyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->deleteJson(route('api.v1.users.vouch.destroy', User::factory()->create()))->assertUnauthorized();
    }

    public function test_returns_204_and_takes_the_points_back(): void
    {
        $voucher = User::factory()->create();
        $student = User::factory()->create();
        $token = JWTAuth::fromUser($voucher);
        $this->withToken($token)->putJson(route('api.v1.users.vouch.store', $student))->assertNoContent();
        $this->assertSame(550, $student->refresh()->trust_score);

        $this->withToken($token)
            ->deleteJson(route('api.v1.users.vouch.destroy', $student))
            ->assertNoContent();

        $this->assertSame(0, Vouch::count());
        $this->assertSame(525, $student->refresh()->trust_score);
    }

    public function test_returns_204_when_there_was_no_vouch(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson(route('api.v1.users.vouch.destroy', User::factory()->create()))
            ->assertNoContent();
    }
}
