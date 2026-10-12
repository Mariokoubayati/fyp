<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UpdateProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->patchJson(route('api.v1.me.update'), ['major' => 'Computer Science'])->assertUnauthorized();
    }

    public function test_returns_200_and_updates_the_profile_fields(): void
    {
        $user = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->patchJson(route('api.v1.me.update'), [
                'name' => 'Sara Haddad',
                'major' => 'Computer Science',
                'study_year' => 3,
                'bio' => 'Looking for a study group for databases.',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sara Haddad')
            ->assertJsonPath('data.major', 'Computer Science')
            ->assertJsonPath('data.study_year', 3);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Sara Haddad', 'study_year' => 3]);
    }

    public function test_returns_200_and_keeps_fields_that_were_not_sent(): void
    {
        $user = User::factory()->create(['major' => 'Architecture']);

        $this->withToken(JWTAuth::fromUser($user))
            ->patchJson(route('api.v1.me.update'), ['bio' => 'Hello'])
            ->assertOk()
            ->assertJsonPath('data.major', 'Architecture');
    }

    public function test_returns_422_when_values_are_invalid(): void
    {
        $user = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($user))
            ->patchJson(route('api.v1.me.update'), ['name' => '', 'study_year' => 9, 'bio' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'study_year', 'bio']);
    }

    public function test_ignores_attempts_to_change_the_email_or_password(): void
    {
        $user = User::factory()->create(['email' => '202311111@ua.edu.lb']);
        $originalPassword = $user->password;

        $this->withToken(JWTAuth::fromUser($user))
            ->patchJson(route('api.v1.me.update'), ['email' => 'other@ua.edu.lb', 'password' => 'Changed-pass-123', 'bio' => 'x'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('202311111@ua.edu.lb', $user->email);
        $this->assertSame($originalPassword, $user->password);
    }
}
