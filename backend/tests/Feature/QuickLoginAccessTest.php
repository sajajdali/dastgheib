<?php

namespace Tests\Feature;

use App\Support\QuickLoginAccess;
use Illuminate\Http\Request;
use Tests\TestCase;

/** ورود سریع با مدیر کل فقط برای IP مجاز یا اجرای محلی. */
class QuickLoginAccessTest extends TestCase
{
    private function request(string $ip, string $host, array $headers = []): Request
    {
        return Request::create("http://{$host}/quick-login", 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            ...$headers,
        ]);
    }

    public function test_only_the_allowed_ip_or_local_development_gets_quick_login(): void
    {
        config(['quick_login.allowed_ips' => ['93.118.157.142']]);

        $this->assertTrue(QuickLoginAccess::allowed($this->request('93.118.157.142', 'clinic.example.com')));
        $this->assertTrue(QuickLoginAccess::allowed($this->request('127.0.0.1', 'clinic1.localhost')));
        $this->assertTrue(QuickLoginAccess::allowed($this->request('::1', 'localhost')));

        $this->assertFalse(QuickLoginAccess::allowed($this->request('5.6.7.8', 'clinic.example.com')));
        $this->assertFalse(QuickLoginAccess::allowed($this->request('5.6.7.8', 'clinic1.localhost')));
        // پشت پروکسی معکوس سرور اصلی، همهٔ درخواست‌ها از 127.0.0.1 می‌آیند؛ این نباید مجاز باشد.
        $this->assertFalse(QuickLoginAccess::allowed($this->request('127.0.0.1', 'clinic.example.com')));
        // هدر جعلی X-Forwarded-For اثری ندارد.
        $this->assertFalse(QuickLoginAccess::allowed($this->request('5.6.7.8', 'clinic.example.com', [
            'HTTP_X_FORWARDED_FOR' => '93.118.157.142',
        ])));
    }
}
