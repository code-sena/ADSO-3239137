<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registration requires all mandatory fields.
     */
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

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects blank values.
     */
    public function test_registration_rejects_blank_values(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '   ',
            'username' => '   ',
            'email' => '   ',
            'password' => '   ',
            'password_confirmation' => '   ',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'username',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects an existing email.
     */
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

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects an existing username.
     */
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

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects passwords shorter than eight characters.
     */
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

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects invalid email formats.
     */
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

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects password confirmation mismatches.
     */
    public function test_registration_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration requires password confirmation.
     */
    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Registration rejects a non-string name.
     */
    public function test_registration_rejects_a_non_string_name(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 12345,
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects a non-string username.
     */
    public function test_registration_rejects_a_non_string_username(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 12345,
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects a non-string email.
     */
    public function test_registration_rejects_a_non_string_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 12345,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects a name exceeding 255 characters.
     */
    public function test_registration_rejects_a_name_exceeding_maximum_length(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => str_repeat('A', 256),
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects a username exceeding 50 characters.
     */
    public function test_registration_rejects_a_username_exceeding_maximum_length(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => str_repeat('a', 51),
            'email' => 'newmanager@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects an email exceeding 255 characters.
     */
    public function test_registration_rejects_an_email_exceeding_maximum_length(): void
    {
        $longEmail = str_repeat('a', 244).'@example.com';

        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => $longEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects a non-string password.
     */
    public function test_registration_rejects_a_non_string_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'newmanager@example.com',
            'password' => ['Password123!'],
            'password_confirmation' => ['Password123!'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}