<?php

namespace Tests\Integration;

use RuntimeException;
use Tests\TestCase;

abstract class RealHttpTestCase extends TestCase
{
    protected string $baseUrl;
    private static bool $serviceChecked = false;
    private static bool $serviceAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = rtrim((string) env('BASE_URL', 'http://127.0.0.1:8000'), '/');
        $this->ensureServiceReady();
    }

    private function ensureServiceReady(int $timeoutSeconds = 30): void
    {
        if (self::$serviceChecked) {
            if (! self::$serviceAvailable) {
                $this->markTestSkipped('HTTP service is not available at '.$this->baseUrl);
            }

            return;
        }

        self::$serviceChecked = true;
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
            $body = @file_get_contents($this->baseUrl.'/up', false, $ctx);
            if ($body !== false) {
                self::$serviceAvailable = true;

                return;
            }
            sleep(1);
        }

        $this->markTestSkipped(
            "HTTP service did not become ready after {$timeoutSeconds}s at {$this->baseUrl}"
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function httpRequest(
        string $method,
        string $path,
        array $data = [],
        array $headers = []
    ): array {
        $url = $this->baseUrl.'/api/v1'.$path;
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, array_map(
            fn (string $k, string $v): string => "{$k}: {$v}",
            array_keys($headers),
            array_values($headers)
        ));

        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $allHeaders),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ];

        if (! empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $options['http']['content'] = (string) json_encode($data);
        }

        $ctx = stream_context_create($options);
        $raw = @file_get_contents($url, false, $ctx);

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 0 Unknown';
        preg_match('/HTTP\/\S+ (\d{3})/', $statusLine, $matches);
        $status = (int) ($matches[1] ?? 0);

        $body = $raw !== false ? (array) (json_decode($raw, true) ?? []) : [];

        return ['status' => $status, 'body' => $body];
    }

    protected function registerUser(string $role = 'rider'): array
    {
        $id = substr(uniqid('', true), -8);
        $username = 'http_test_'.$id;
        $password = 'Password1234';

        $result = $this->httpRequest('POST', '/auth/register', [
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $password,
            'role' => $role,
        ]);

        if ($result['status'] !== 201) {
            throw new RuntimeException('Registration failed: '.json_encode($result['body']));
        }

        return [
            'username' => $username,
            'password' => $password,
            'token' => (string) ($result['body']['token'] ?? ''),
            'user' => $result['body']['user'] ?? [],
        ];
    }

    protected function loginUser(string $username, string $password): string
    {
        $result = $this->httpRequest('POST', '/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);

        if ($result['status'] !== 200) {
            throw new RuntimeException('Login failed: '.json_encode($result['body']));
        }

        return (string) ($result['body']['token'] ?? '');
    }

    protected function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
