<?php

namespace Tests\Feature;

use App\Models\DynamicReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynamicReportExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\EnforceTenantStorageQuota::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        (require database_path('migrations/tenant/2026_09_13_150000_create_dynamic_report_tables.php'))->up();
        $user = new User();
        $user->id = 1;
        $this->actingAs($user);
    }

    public function test_export_contains_every_filtered_report_row_regardless_of_display_page(): void
    {
        $report = DynamicReport::query()->create([
            'user_id' => 1,
            'status' => 'completed',
            'columns' => ['name', 'phone'],
            'filters' => ['scope' => 'patients', 'multi' => ['status' => ['آمد']]],
            'total_rows' => 235,
        ]);
        $rows = [];
        for ($index = 1; $index <= 235; $index++) {
            $rows[] = [
                'report_id' => $report->id,
                'patient_id' => $index,
                'payload' => json_encode([
                    'name' => "ردیف {$index}",
                    'phone' => $index === 235 ? '<script>unsafe</script>' : "0912{$index}",
                ], JSON_UNESCAPED_UNICODE),
            ];
        }
        DB::table('dynamic_report_rows')->insert($rows);

        $this->getJson("/api/report-builder/reports/{$report->id}/rows?page=2&per_page=10")
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $response = $this->get("/api/report-builder/reports/{$report->id}/export?page=2&per_page=10");
        $response->assertOk()->assertDownload('clinic-report.xls');
        $content = $response->streamedContent();

        $this->assertSame(236, substr_count($content, '<tr>'));
        $this->assertStringContainsString('ردیف 1', $content);
        $this->assertStringContainsString('ردیف 235', $content);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $content);
    }

    public function test_export_rejects_reports_owned_by_another_user(): void
    {
        $report = DynamicReport::query()->create([
            'user_id' => 2,
            'status' => 'completed',
            'columns' => ['name'],
            'filters' => [],
        ]);

        $this->get("/api/report-builder/reports/{$report->id}/export")->assertForbidden();
    }
}
