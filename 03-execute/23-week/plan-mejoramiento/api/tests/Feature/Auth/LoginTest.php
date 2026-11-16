<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
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
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJsonMissingPath('user.password');
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
}