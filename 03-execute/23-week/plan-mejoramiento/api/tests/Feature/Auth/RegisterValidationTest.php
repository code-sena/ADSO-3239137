<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_all_mandatory_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'username',
                'email',
                'password',
            ]);
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::create([
            'name' => 'Existing Manager',
            'username' => 'existingmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_an_existing_username(): void
    {
        User::create([
            'name' => 'Existing Manager',
            'username' => 'existingmanager',
            'email' => 'manager@example.com',
            'password' => 'Password123!',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'existingmanager',
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_rejects_a_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_an_invalid_email_format(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'correo-invalido',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}