<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UserSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.users.search', ['search' => 'sara']))->assertUnauthorized();
    }

    public function test_finds_verified_students_by_name_without_exposing_their_email(): void
    {
        $searcher = User::factory()->create(['name' => 'Sara Searcher']);
        $match = User::factory()->create(['name' => 'Sarah Haddad']);
        User::factory()->create(['name' => 'Karim Nassar']);

        $this->withToken(JWTAuth::fromUser($searcher))
            ->getJson(route('api.v1.users.search', ['search' => 'sarah']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_excludes_the_searching_student_and_unverified_accounts(): void
    {
        $searcher = User::factory()->create(['name' => 'Sara Searcher']);
        User::factory()->unverified()->create(['name' => 'Sara Unverified']);

        $this->withToken(JWTAuth::fromUser($searcher))
            ->getJson(route('api.v1.users.search', ['search' => 'sara']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_nothing_for_queries_shorter_than_two_characters(): void
    {
        User::factory()->create(['name' => 'Sara Haddad']);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(route('api.v1.users.search', ['search' => 's']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
