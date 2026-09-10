<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_application_uses_tehran_timezone(): void
    {
        $this->assertSame('Asia/Tehran', config('app.timezone'));
        $this->assertSame('Asia/Tehran', date_default_timezone_get());
        $this->assertSame('+03:30', now()->format('P'));
    }
}
