<?php

namespace Tests\Feature;

use App\Models\DynamicReport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('customer_level')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('profile_thumbnail_path')->nullable();
            $table->softDeletes();
        });
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

    public function test_rows_include_patient_profile_metadata_without_exporting_it_as_a_column(): void
    {
        $report = DynamicReport::query()->create([
            'user_id' => 1,
            'status' => 'completed',
            'columns' => ['name'],
            'filters' => ['scope' => 'patients'],
            'total_rows' => 1,
        ]);
        DB::table('dynamic_report_rows')->insert([
            'report_id' => $report->id,
            'patient_id' => 42,
            'payload' => json_encode([
                'name' => 'سارا',
                '_patient' => [
                    'id' => 42,
                    'file_number' => '1042',
                    'first_name' => 'سارا',
                    'last_name' => 'محمدی',
                    'avatar_url' => '/storage/patients/42/thumb.webp',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $this->getJson("/api/report-builder/reports/{$report->id}/rows")
            ->assertOk()
            ->assertJsonPath('data.0._patient.id', 42)
            ->assertJsonPath('data.0._patient.avatar_url', '/storage/patients/42/thumb.webp');

        $content = $this->get("/api/report-builder/reports/{$report->id}/export")->streamedContent();
        $this->assertStringNotContainsString('_patient', $content);
        $this->assertStringNotContainsString('thumb.webp', $content);
    }

    public function test_national_id_is_exported_immediately_after_phone_when_selected(): void
    {
        $report = DynamicReport::query()->create([
            'user_id' => 1,
            'status' => 'completed',
            'columns' => ['phone', 'nationalId', 'fileNo'],
            'filters' => ['scope' => 'patients'],
            'total_rows' => 1,
        ]);
        DB::table('dynamic_report_rows')->insert([
            'report_id' => $report->id,
            'patient_id' => 1,
            'payload' => json_encode([
                'phone' => '09121234567',
                'nationalId' => '0012345678',
                'fileNo' => '1042',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $content = $this->get("/api/report-builder/reports/{$report->id}/export")->streamedContent();
        $this->assertStringContainsString('<th>شماره تماس</th><th>کد ملی</th><th>شماره پرونده</th>', $content);
        $this->assertStringContainsString('<td>09121234567</td><td>0012345678</td><td>1042</td>', $content);
    }

    public function test_rows_hydrate_current_patient_photo_for_legacy_report_payloads(): void
    {
        DB::table('patients')->insert([
            'id' => 42,
            'file_number' => '1042',
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'customer_level' => 'gold',
            'profile_photo_path' => 'patients/42/photo.webp',
            'profile_thumbnail_path' => 'patients/42/thumb.webp',
        ]);
        $report = DynamicReport::query()->create([
            'user_id' => 1,
            'status' => 'completed',
            'columns' => ['name'],
            'filters' => ['scope' => 'patients'],
            'total_rows' => 1,
        ]);
        DB::table('dynamic_report_rows')->insert([
            'report_id' => $report->id,
            'patient_id' => 42,
            'payload' => json_encode(['name' => 'سارا'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->getJson("/api/report-builder/reports/{$report->id}/rows")
            ->assertOk()
            ->assertJsonPath('data.0._patient.id', 42)
            ->assertJsonPath('data.0._patient.customer_level', 'gold')
            ->assertJsonPath('data.0._patient.avatar_url', Storage::disk('public')->url('patients/42/thumb.webp'));
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
