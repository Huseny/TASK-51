<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    public function test_sanctum_token_lifetime_is_configured_for_twelve_hours(): void
    {
        $this->assertSame(720, (int) config('sanctum.expiration'));
    }

    public function test_sanctum_stateful_cookie_endpoint_remains_available_for_deprecated_clients(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
    }
}
