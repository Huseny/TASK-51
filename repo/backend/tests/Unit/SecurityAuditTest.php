<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_hashed_and_sensitive_fields_hidden_and_encrypted_at_rest(): void
    {
        $user = User::factory()->create([
            'password' => 'Password12345',
            'email' => 'audit@example.com',
            'phone' => '1234567890',
        ]);

        $this->assertNotSame('Password12345', $user->password);
        $this->assertTrue(password_verify('Password12345', $user->password));

        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('phone', $array);

        $raw = DB::table('users')->where('id', $user->id)->first(['email', 'phone']);
        $this->assertNotSame('audit@example.com', $raw->email);
        $this->assertNotSame('1234567890', $raw->phone);
    }

    public function test_logs_do_not_include_password_or_token_keywords_in_log_calls(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (! is_string($content)) {
                continue;
            }

            $lines = preg_split('/\R/', $content) ?: [];
            foreach ($lines as $lineNumber => $line) {
                if (str_contains($line, 'Log::') && preg_match('/password|token|plainTextToken/i', $line)) {
                    $offenders[] = $file->getPathname().':'.($lineNumber + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Found potentially sensitive keywords in log calls: '.implode(', ', $offenders));
    }
}
