<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class UnauthenticatedApiTest extends TestCase
{
    public function test_a_request_without_a_token_gets_401_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/courses')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_an_invalid_token_gets_401(): void
    {
        $this->withToken('not-a-real-token')->get('/api/v1/me')->assertUnauthorized();
    }
}
