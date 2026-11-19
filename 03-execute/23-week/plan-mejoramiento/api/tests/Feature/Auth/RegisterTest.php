<?php

namespace tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_manager(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Manager',
            'username' => 'testmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Test Manager')
            ->assertJsonPath('user.username', 'testmanager')
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        // Verify that the user was registered as a manager.
        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);

        // Verify that exactly one user was created.
        $this->assertDatabaseCount('users', 1);

        // Verify that the password is stored as a secure hash.
        $user = User::where('email', 'manager@example.com')->firstOrFail();

        $this->assertNotEquals('Password123!', $user->password);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        // Verify that exactly one access token was generated.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobility-aids-token',
        ]);

        // Verify that the registration response contains a token.
        $token = $response->json('token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify that the token is stored using SHA-256.
        $tokenParts = explode('|', $token, 2);

        $this->assertCount(2, $tokenParts);

        $storedToken = DB::table('personal_access_tokens')
            ->where('id', (int) $tokenParts[0])
            ->value('token');

        $this->assertNotNull($storedToken);
        $this->assertSame(
            hash('sha256', $tokenParts[1]),
            $storedToken
        );

        // Verify that the generated token grants access to protected routes.
        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('role', 'manager')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    /**
     * Registration assigns the manager role when no role is provided.
     */
    public function test_user_can_register_without_sending_a_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Manager Without Role',
            'username' => 'managerwithoutrole',
            'email' => 'withoutrole@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Manager Without Role')
            ->assertJsonPath('user.username', 'managerwithoutrole')
            ->assertJsonPath('user.email', 'withoutrole@example.com')
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        // Verify that the user was saved with the manager role.
        $this->assertDatabaseHas('users', [
            'email' => 'withoutrole@example.com',
            'role' => 'manager',
        ]);

        // Verify that exactly one user was created.
        $this->assertDatabaseCount('users', 1);

        // Verify that exactly one access token was generated.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}