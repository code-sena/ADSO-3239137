<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ])
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);
    }
}