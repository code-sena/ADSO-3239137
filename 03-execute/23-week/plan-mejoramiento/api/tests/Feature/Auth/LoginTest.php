<?php

namespace tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Test Manager',
            'username' => 'testmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonPath('user.username', 'testmanager')
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'mobility-aids-token',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'manager@example.com')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'name' => 'Test Manager',
            'username' => 'testmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_empty_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => '',
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_empty_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_numeric_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 12345,
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_email_array(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => ['manager@example.com'],
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_password_array(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => ['Password123!'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}