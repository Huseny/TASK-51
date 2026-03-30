<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_registration_returns_user_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'new_rider',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'role' => 'rider',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'username', 'role']])
            ->assertJsonMissingPath('token');
    }

    public function test_duplicate_username_returns_validation_error(): void
    {
        User::factory()->create(['username' => 'rider01']);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'rider01',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'role' => 'rider',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_password_under_ten_characters_returns_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'shortpass',
            'password' => 'Pass123',
            'password_confirmation' => 'Pass123',
            'role' => 'rider',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_password_without_letter_returns_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'numbers_only',
            'password' => '1234567890',
            'password_confirmation' => '1234567890',
            'role' => 'rider',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_password_without_number_returns_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'letters_only',
            'password' => 'PasswordOnly',
            'password_confirmation' => 'PasswordOnly',
            'role' => 'rider',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_missing_required_fields_return_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_invalid_role_returns_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'bad_role',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_registered_user_is_authenticated_via_session_immediately(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'instant_login',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
            'role' => 'driver',
        ])->assertStatus(201);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.username', 'instant_login');
    }
}
