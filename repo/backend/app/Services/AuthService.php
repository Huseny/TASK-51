<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    private int $lockoutAttempts;

    private int $lockoutMinutes;

    public function __construct()
    {
        $this->lockoutAttempts = (int) env('AUTH_LOCKOUT_ATTEMPTS', 5);
        $this->lockoutMinutes = (int) env('AUTH_LOCKOUT_MINUTES', 15);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{user: User, token: string, token_type: string, expires_at: string|null}
     */
    public function register(array $data): array
    {
        $user = User::query()->create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        Log::channel('auth')->info('User registered successfully', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ]);

        return [
            'user' => $user,
            ...$this->issueTokenPayload($user),
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function login(string $username, string $password): array
    {
        $user = User::query()->where('username', $username)->first();

        if (! $user) {
            Log::channel('auth')->warning('Failed login attempt for unknown user', [
                'username' => $username,
            ]);

            return $this->invalidCredentialsResponse();
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return [
                'status' => 429,
                'body' => [
                    'error' => 'account_locked',
                    'message' => sprintf('Account locked. Try again after %s', $user->locked_until->toDateTimeString()),
                    'locked_until' => $user->locked_until->toISOString(),
                ],
            ];
        }

        if (! Hash::check($password, $user->password)) {
            $attemptCount = min(255, $user->failed_login_attempts + 1);
            $user->failed_login_attempts = $attemptCount;

            if ($attemptCount >= $this->lockoutAttempts) {
                $user->locked_until = Carbon::now()->addMinutes($this->lockoutMinutes);
            }

            $user->save();

            Log::channel('auth')->warning(
                sprintf('Failed login attempt for user %s (attempt #%d)', $user->username, $attemptCount),
                ['user_id' => $user->id, 'username' => $user->username, 'attempt_count' => $attemptCount]
            );

            if ($attemptCount >= $this->lockoutAttempts) {
                Log::channel('security')->critical(
                    sprintf('Account locked: %s after %d failed attempts', $user->username, $attemptCount),
                    ['user_id' => $user->id, 'username' => $user->username, 'attempt_count' => $attemptCount]
                );
            }

            return $this->invalidCredentialsResponse();
        }

        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = Carbon::now();
        $user->save();

        Log::channel('auth')->info(sprintf('User %s logged in successfully', $user->username), [
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        return [
            'status' => 200,
            'body' => [
                'user' => $user,
                ...$this->issueTokenPayload($user),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array{error: string, message: string}}
     */
    private function invalidCredentialsResponse(): array
    {
        return [
            'status' => 401,
            'body' => [
                'error' => 'invalid_credentials',
                'message' => 'Invalid username or password',
            ],
        ];
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string|null}
     */
    private function issueTokenPayload(User $user): array
    {
        $expirationMinutes = (int) config('sanctum.expiration', 720);
        $expiresAt = $expirationMinutes > 0
            ? now()->addMinutes($expirationMinutes)
            : null;

        /** @var NewAccessToken $token */
        $token = $user->createToken('auth', ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }
}
