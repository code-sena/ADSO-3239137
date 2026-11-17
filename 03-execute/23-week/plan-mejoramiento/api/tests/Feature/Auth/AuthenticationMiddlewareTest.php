<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A guest cannot access protected routes.
     */
    public function test_guest_cannot_access_authenticated_user_route(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * An invalid token cannot access protected routes.
     */
    public function test_user_cannot_access_authenticated_route_with_invalid_token(): void
    {
        $response = $this->withToken('invalid-token')
            ->getJson('/api/user');

        $response->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * An authenticated user can access protected routes with a valid token.
     */
    public function test_user_can_access_authenticated_route_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Test Manager',
            'username' => 'testmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withToken($token)
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'username',
                'email',
                'role',
                'created_at',
                'updated_at',
            ])
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', 'Test Manager')
            ->assertJsonPath('username', 'testmanager')
            ->assertJsonPath('email', 'manager@example.com')
            ->assertJsonPath('role', 'manager')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /**
     * An expired token cannot access protected routes.
     */
    public function test_user_cannot_access_authenticated_route_with_expired_token(): void
    {
        $user = User::create([
            'name' => 'Expired Manager',
            'username' => 'expiredmanager',
            'email' => 'expired@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $token = $user->createToken(
            'expired-token',
            ['*'],
            now()->subMinute()
        )->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/user');

        $response->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}