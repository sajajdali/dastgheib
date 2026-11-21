<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class SanctumStatefulDomainsTest extends TestCase
{
    public function test_same_origin_tenant_requests_use_the_session(): void
    {
        $request = Request::create('https://demo.tenant.test/api/auth/user', server: [
            'HTTP_REFERER' => 'https://demo.tenant.test/',
        ]);

        $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend($request));
    }

    public function test_unconfigured_cross_origin_requests_do_not_use_the_session(): void
    {
        $request = Request::create('https://demo.tenant.test/api/auth/user', server: [
            'HTTP_ORIGIN' => 'https://external.tenant.test',
        ]);

        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend($request));
    }
}
