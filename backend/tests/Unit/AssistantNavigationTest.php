<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class AssistantNavigationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_doctor_creation_request_returns_exact_resources_doctor_destination(): void
    {
        $manager = Mockery::mock(User::class);
        $manager->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['settings.view', 'resources.view'], true));
        $result = app(AssistantToolExecutor::class)->execute($manager, 'open_clinic_page', [
            'page'=>'settings_doctors', 'date'=>null, 'doctor'=>null,
        ]);

        $this->assertSame('settings_doctors', $result['presentation']['page']);
        $this->assertStringContainsString('منابع ← پزشک', $result['presentation']['label']);

        $unauthorized = Mockery::mock(User::class);
        $unauthorized->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'resources.view');
        $this->expectException(InvalidArgumentException::class);
        app(AssistantToolExecutor::class)->execute($unauthorized, 'open_clinic_page', ['page'=>'settings_doctors']);
    }
}
