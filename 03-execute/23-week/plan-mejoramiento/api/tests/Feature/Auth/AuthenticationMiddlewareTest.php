<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_authenticated_user_route(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_user_cannot_access_authenticated_route_with_invalid_token(): void
    {
        $response = $this->withToken('invalid-token')
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }
}