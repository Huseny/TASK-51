<?php

namespace Tests\Integration;

class HttpIntegrationTest extends RealHttpTestCase
{
    public function test_service_health_endpoint_responds(): void
    {
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($this->baseUrl.'/up', false, $ctx);

        $this->assertNotFalse($body, 'Health endpoint /up should return a response');
    }

    public function test_register_and_login_flow(): void
    {
        $user = $this->registerUser('rider');

        $this->assertNotEmpty($user['token'], 'Registration should return a bearer token');
        $this->assertNotEmpty($user['username']);

        $loginToken = $this->loginUser($user['username'], $user['password']);
        $this->assertNotEmpty($loginToken, 'Login should return a bearer token');
    }

    public function test_authenticated_me_endpoint_returns_current_user(): void
    {
        $user = $this->registerUser('rider');

        $result = $this->httpRequest('GET', '/auth/me', [], $this->authHeaders($user['token']));

        $this->assertSame(200, $result['status']);
        $this->assertSame($user['username'], $result['body']['user']['username'] ?? null);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $result = $this->httpRequest('GET', '/auth/me');

        $this->assertSame(401, $result['status']);
        $this->assertSame('unauthenticated', $result['body']['error'] ?? null);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = $this->registerUser('driver');

        $meResult = $this->httpRequest('GET', '/auth/me', [], $this->authHeaders($user['token']));
        $this->assertSame(200, $meResult['status']);

        $logoutResult = $this->httpRequest('POST', '/auth/logout', [], $this->authHeaders($user['token']));
        $this->assertSame(200, $logoutResult['status']);

        $meAfterLogout = $this->httpRequest('GET', '/auth/me', [], $this->authHeaders($user['token']));
        $this->assertSame(401, $meAfterLogout['status']);
    }

    public function test_invalid_login_credentials_return_401(): void
    {
        $result = $this->httpRequest('POST', '/auth/login', [
            'username' => 'no_such_user_'.uniqid('', true),
            'password' => 'WrongPass99!',
        ]);

        $this->assertSame(401, $result['status']);
    }

    public function test_registration_validation_rejects_short_password(): void
    {
        $result = $this->httpRequest('POST', '/auth/register', [
            'username' => 'shortpw_'.uniqid('', true),
            'password' => 'abc',
            'password_confirmation' => 'abc',
            'role' => 'rider',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('validation_error', $result['body']['error'] ?? null);
    }

    public function test_notification_unread_count_requires_auth(): void
    {
        $result = $this->httpRequest('GET', '/notifications/unread-count');

        $this->assertSame(401, $result['status']);
    }

    public function test_authenticated_user_can_fetch_empty_notifications(): void
    {
        $user = $this->registerUser('rider');

        $result = $this->httpRequest('GET', '/notifications', [], $this->authHeaders($user['token']));

        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('data', $result['body']);
        $this->assertArrayHasKey('total', $result['body']);
    }

    public function test_ride_order_creation_requires_rider_role(): void
    {
        $driver = $this->registerUser('driver');

        $result = $this->httpRequest('POST', '/ride-orders', [
            'origin_address' => '123 Main St',
            'destination_address' => 'Airport',
            'rider_count' => 1,
            'time_window_start' => date('Y-m-d H:i', strtotime('+1 hour')),
            'time_window_end' => date('Y-m-d H:i', strtotime('+2 hours')),
        ], $this->authHeaders($driver['token']));

        $this->assertSame(403, $result['status']);
    }

    public function test_rider_can_create_and_view_ride_order(): void
    {
        $rider = $this->registerUser('rider');

        $createResult = $this->httpRequest('POST', '/ride-orders', [
            'origin_address' => '10 Elm Street',
            'destination_address' => 'Central Station',
            'rider_count' => 2,
            'time_window_start' => date('Y-m-d H:i', strtotime('+1 hour')),
            'time_window_end' => date('Y-m-d H:i', strtotime('+3 hours')),
        ], $this->authHeaders($rider['token']));

        $this->assertSame(201, $createResult['status']);
        $this->assertArrayHasKey('order', $createResult['body']);

        $orderId = $createResult['body']['order']['id'] ?? null;
        $this->assertNotNull($orderId);
        $this->assertSame('matching', $createResult['body']['order']['status'] ?? null);

        $showResult = $this->httpRequest('GET', '/ride-orders/'.$orderId, [], $this->authHeaders($rider['token']));
        $this->assertSame(200, $showResult['status']);
        $this->assertSame($orderId, $showResult['body']['order']['id'] ?? null);
    }

    public function test_driver_can_see_available_rides(): void
    {
        $rider = $this->registerUser('rider');
        $driver = $this->registerUser('driver');

        $this->httpRequest('POST', '/ride-orders', [
            'origin_address' => '5 Oak Ave',
            'destination_address' => 'Harbor Terminal',
            'rider_count' => 1,
            'time_window_start' => date('Y-m-d H:i', strtotime('+1 hour')),
            'time_window_end' => date('Y-m-d H:i', strtotime('+2 hours')),
        ], $this->authHeaders($rider['token']));

        $result = $this->httpRequest('GET', '/driver/available-rides', [], $this->authHeaders($driver['token']));

        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('data', $result['body']);
    }

    public function test_readiness_endpoint_reports_schema_status(): void
    {
        $result = $this->httpRequest('GET', '/readiness');

        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('status', $result['body']);
    }
}
