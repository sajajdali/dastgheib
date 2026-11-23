<?php

namespace Tests\Feature;

use App\Models\ReportBuilderTemplate;
use App\Models\User;
use Tests\TestCase;

class ReportBuilderTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\EnsureTenantSubscriptionActive::class,
            \App\Http\Middleware\EnforceTenantStorageQuota::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        (require database_path('migrations/tenant/2026_11_23_140000_create_report_builder_templates_table.php'))->up();

        $user = new User();
        $user->id = 7;
        $user->name = 'مینو';
        $this->actingAs($user);
    }

    public function test_store_lists_and_deletes_clinic_templates(): void
    {
        $create = $this->postJson('/api/report-builder/templates', [
            'name' => 'قالب مینو',
            'source' => 'appointments',
            'columns' => ['name', 'family', 'amount'],
        ]);
        $create->assertCreated();
        $id = $create->json('template.id');
        $this->assertNotEmpty($id);
        $this->assertSame('مینو', $create->json('template.creator_name'));

        $list = $this->getJson('/api/report-builder/templates');
        $list->assertOk();
        $this->assertCount(1, $list->json('templates'));
        $this->assertSame('قالب مینو', $list->json('templates.0.label'));

        $this->deleteJson('/api/report-builder/templates/'.$id)->assertOk();
        $this->assertSame(0, ReportBuilderTemplate::query()->count());
    }

    public function test_migrate_dedupes_by_client_key_and_fingerprint(): void
    {
        $first = $this->postJson('/api/report-builder/templates/migrate', [
            'templates' => [[
                'client_key' => 'c111',
                'name' => 'ماهانه لیزر',
                'source' => 'patients',
                'columns' => ['fileNo', 'name', 'family'],
            ]],
        ]);
        $first->assertOk();
        $this->assertSame(1, $first->json('created_count'));

        $second = $this->postJson('/api/report-builder/templates/migrate', [
            'templates' => [
                [
                    'client_key' => 'c111',
                    'name' => 'ماهانه لیزر',
                    'source' => 'patients',
                    'columns' => ['fileNo', 'name', 'family'],
                ],
                [
                    'client_key' => 'c222',
                    'name' => 'ماهانه لیزر',
                    'source' => 'patients',
                    'columns' => ['fileNo', 'name', 'family'],
                ],
                [
                    'client_key' => 'c333',
                    'name' => 'حقوق پزشکان',
                    'source' => 'appointments',
                    'columns' => ['doctor', 'salary'],
                ],
            ],
        ]);
        $second->assertOk();
        $this->assertSame(1, $second->json('created_count'));
        $this->assertSame(2, $second->json('skipped_count'));
        $this->assertSame(2, ReportBuilderTemplate::query()->count());
    }
}
