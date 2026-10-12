<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Vouch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StudentProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.users.show', User::factory()->create()))->assertUnauthorized();
    }

    public function test_returns_the_public_profile_without_the_email(): void
    {
        $student = User::factory()->create(['name' => 'Karim Nassar', 'major' => 'Architecture', 'bio' => 'Studio rat']);
        $viewer = User::factory()->create();
        $voucher = new Vouch;
        $voucher->voucher_id = $viewer->id;
        $voucher->vouchee_id = $student->id;
        $voucher->save();

        $this->withToken(JWTAuth::fromUser($viewer))
            ->getJson(route('api.v1.users.show', $student))
            ->assertOk()
            ->assertJsonPath('data.name', 'Karim Nassar')
            ->assertJsonPath('data.major', 'Architecture')
            ->assertJsonPath('data.bio', 'Studio rat')
            ->assertJsonPath('data.trust.tier', 'basic')
            ->assertJsonPath('data.vouch_count', 1)
            ->assertJsonPath('data.vouched_by_me', true)
            ->assertJsonMissingPath('data.email');
    }

    public function test_returns_404_when_the_student_does_not_exist(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.users.show', 9999))
            ->assertNotFound();
    }
}
