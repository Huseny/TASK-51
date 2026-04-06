<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_valid_credentials_return_user_with_bearer_token(): void
    {
        User::factory()->create([
            'username' => 'driver01',
            'password' => Hash::make('Driver1234!'),
            'role' => 'driver',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'driver01',
            'password' => 'Driver1234!',
        ])->assertStatus(200)
            ->assertJsonStructure(['user', 'token', 'token_type', 'expires_at'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_wrong_password_returns_generic_message(): void
    {
        User::factory()->create([
            'username' => 'rider01',
            'password' => Hash::make('Rider12345!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'rider01',
            'password' => 'WrongPassword123',
        ])->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid username or password');
    }

    public function test_non_existent_username_returns_same_generic_message(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'username' => 'ghost',
            'password' => 'WrongPassword123',
        ])->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid username or password');
    }

    public function test_sixth_attempt_returns_account_locked_response(): void
    {
        User::factory()->create([
            'username' => 'lock_me',
            'password' => Hash::make('Password1234!'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'lock_me',
                'password' => 'WrongPassword123',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'username' => 'lock_me',
            'password' => 'WrongPassword123',
        ])->assertStatus(429)
            ->assertJsonPath('error', 'account_locked')
            ->assertJsonStructure(['locked_until']);
    }

    public function test_lockout_expires_after_fifteen_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 10:00:00'));

        User::factory()->create([
            'username' => 'time_locked',
            'password' => Hash::make('Password1234!'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'time_locked',
                'password' => 'WrongPassword123',
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-03-25 10:16:00'));

        $this->postJson('/api/v1/auth/login', [
            'username' => 'time_locked',
            'password' => 'Password1234!',
        ])->assertStatus(200)
            ->assertJsonStructure(['user', 'token', 'token_type', 'expires_at'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_successful_login_resets_failed_attempt_counter(): void
    {
        $user = User::factory()->create([
            'username' => 'reset_counter',
            'password' => Hash::make('Password1234!'),
            'failed_login_attempts' => 3,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'reset_counter',
            'password' => 'Password1234!',
        ])->assertStatus(200);

        $user->refresh();

        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_login_token_can_access_me_endpoint_and_logout_revokes_it(): void
    {
        User::factory()->create([
            'username' => 'api_driver',
            'password' => Hash::make('Driver1234!'),
            'role' => 'driver',
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'username' => 'api_driver',
            'password' => 'Driver1234!',
        ])->assertOk()->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'api_driver');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_token_expires_after_twelve_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 10:00:00'));

        $user = User::factory()->create([
            'username' => 'expiring_token',
            'password' => Hash::make('Password1234!'),
        ]);

        $token = $user->createToken('auth', ['*'], now()->addHours(12))->plainTextToken;

        Carbon::setTestNow(Carbon::parse('2026-03-25 23:01:00'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
