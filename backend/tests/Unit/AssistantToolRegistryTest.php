<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolRegistry;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AssistantToolRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_only_permitted_tools_are_advertised_with_strict_schemas(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'appointments.view');

        $definitions = (new AssistantToolRegistry)->definitions($user);
        $names = array_column($definitions, 'name');
        $this->assertContains('count_appointments', $names);
        $this->assertContains('count_doctors', $names);
        $this->assertNotContains('get_financial_metric', $names);
        $this->assertNotContains('count_patients', $names);
        foreach ($definitions as $definition) {
            $this->assertTrue($definition['strict']);
            $this->assertFalse($definition['parameters']['additionalProperties']);
            $this->assertSame(array_keys((array) $definition['parameters']['properties']), $definition['parameters']['required']);
        }
    }

    public function test_resources_user_can_count_doctors_without_appointment_access(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'resources.view');

        $registry = new AssistantToolRegistry;
        $this->assertTrue($registry->allowed($user, 'count_doctors'));
        $this->assertTrue($registry->allowed($user, 'find_doctors'));
        $this->assertFalse($registry->allowed($user, 'count_appointments'));
    }

    public function test_doctor_revenue_requires_both_financial_and_doctor_report_permissions(): void
    {
        $financialOnly = Mockery::mock(User::class);
        $financialOnly->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'reports.financial');
        $both = Mockery::mock(User::class);
        $both->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['reports.financial','reports.doctors'], true));

        $registry = new AssistantToolRegistry;
        $this->assertFalse($registry->allowed($financialOnly, 'get_doctor_revenue'));
        $this->assertTrue($registry->allowed($both, 'get_doctor_revenue'));
    }

    public function test_navigation_schema_exposes_exact_nested_destinations_only_with_permissions(): void
    {
        $manager = Mockery::mock(User::class);
        $manager->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['settings.view', 'resources.view'], true));
        $doctorOnly = Mockery::mock(User::class);
        $doctorOnly->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'resources.view');

        $tool = collect((new AssistantToolRegistry)->definitions($manager))->firstWhere('name', 'open_clinic_page');
        $destinations = $tool['parameters']['properties']['page']['enum'];
        $this->assertContains('settings_doctors', $destinations);
        $this->assertContains('settings_staff', $destinations);
        $this->assertContains('settings_channels', $destinations);
        $this->assertContains('settings_roles', $destinations);
        $this->assertNotContains('settings_doctors', array_keys((new \App\Services\Assistant\AssistantNavigationCatalog)->allowed($doctorOnly)));
    }
}
