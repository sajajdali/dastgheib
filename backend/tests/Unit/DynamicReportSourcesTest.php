<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Jobs\GenerateDynamicReport;
use App\Models\DynamicReport;
use App\Models\Patient;
use App\Reporting\DynamicReports\Sources\AppointmentReportSource;
use App\Reporting\DynamicReports\Sources\PatientReportSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicReportSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('patients', function (Blueprint $table) {
            $table->id(); $table->string('first_name')->nullable(); $table->string('last_name')->nullable();
            $table->string('phone')->nullable(); $table->string('file_number')->nullable();
            $table->string('gender')->nullable(); $table->string('city')->nullable();
            $table->date('birth_date')->nullable(); $table->string('financial_status')->nullable();
            $table->string('customer_level')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('file_number')->nullable(); $table->string('phone')->nullable();
            $table->string('lastname')->nullable(); $table->string('gender')->nullable();
            $table->string('month')->nullable(); $table->integer('day_num')->nullable();
            $table->string('status')->nullable(); $table->string('referrer_phone')->nullable();
            $table->string('done')->nullable(); $table->string('amount')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }

    public function test_appointment_source_keeps_two_appointments_of_one_patient_as_two_rows(): void
    {
        $patientId = DB::table('patients')->insertGetId([
            'first_name' => 'سارا', 'last_name' => 'محمدی', 'phone' => '09120000000',
            'file_number' => '1001', 'customer_level' => 'problematic',
        ]);
        $patient = Patient::query()->findOrFail($patientId);
        $firstId = DB::table('appointments')->insertGetId(['file_number' => '1001', 'phone' => $patient->phone, 'month' => '1405-07', 'day_num' => 10, 'status' => 'آمد']);
        $secondId = DB::table('appointments')->insertGetId(['file_number' => '1001', 'phone' => $patient->phone, 'month' => '1405-07', 'day_num' => 11, 'status' => 'آمد']);
        $first = Appointment::query()->findOrFail($firstId);
        $second = Appointment::query()->findOrFail($secondId);

        $source = app(AppointmentReportSource::class);
        $rows = $source->rows(collect([$first, $second]), []);
        $this->assertCount(2, $rows);
        $this->assertSame([$first->id, $second->id], $rows->pluck('source_id')->all());
        $this->assertSame([$patient->id, $patient->id], $rows->pluck('patient_id')->all());
        $this->assertSame([$first->id, $second->id], $rows->map(fn ($row) => $row['context']->latestAppointment->id)->all());
    }

    public function test_source_queries_do_not_conflate_patient_rows_with_appointment_rows(): void
    {
        $filters = ['scope' => 'appointments', 'reportDate' => ['from' => '1405-07-01', 'to' => '1405-07-30']];
        $appointmentSql = app(AppointmentReportSource::class)->query($filters)->toSql();
        $patientSql = app(PatientReportSource::class)->query(['scope' => 'patients', 'reportDate' => $filters['reportDate']])->toSql();
        $this->assertStringContainsString('from "appointments"', $appointmentSql);
        $this->assertStringNotContainsString('from "patients"', $appointmentSql);
        $this->assertStringContainsString('from "patients"', $patientSql);
        $this->assertStringNotContainsString('report_range_appointments', $patientSql);
    }

    public function test_appointment_filters_apply_to_the_same_appointment_row(): void
    {
        $query = app(AppointmentReportSource::class)->query([
            'reportDate' => ['from' => '1405-07-01', 'to' => '1405-07-30'],
            'multi' => ['status' => ['آمد'], 'source' => ['اینستاگرام']],
        ]);
        $sql = $query->toSql();
        $this->assertStringContainsString('"appointments"."status" in', $sql);
        $this->assertStringContainsString('"appointments"."source" in', $sql);
        $this->assertStringNotContainsString('status_appointments', $sql);
        $this->assertStringNotContainsString('source_appointments', $sql);
    }

    public function test_migration_preserves_old_rows_and_allows_multiple_appointments_for_a_patient(): void
    {
        Schema::create('dynamic_report_rows', function (Blueprint $table) {
            $table->id(); $table->uuid('report_id'); $table->unsignedBigInteger('patient_id');
            $table->json('payload'); $table->timestamp('created_at')->nullable();
            $table->unique(['report_id', 'patient_id']);
        });
        $reportId = '11111111-1111-1111-1111-111111111111';
        DB::table('dynamic_report_rows')->insert(['report_id' => $reportId, 'patient_id' => 1, 'payload' => '{}']);
        $migration = require database_path('migrations/tenant/2026_10_17_120000_support_appointment_rows_in_dynamic_reports.php');
        $migration->up();
        $this->assertDatabaseHas('dynamic_report_rows', ['report_id' => $reportId, 'patient_id' => 1, 'source_type' => 'patients', 'source_id' => 1]);
        DB::table('dynamic_report_rows')->insert([
            ['report_id' => $reportId, 'patient_id' => 1, 'source_type' => 'appointments', 'source_id' => 10, 'payload' => '{}'],
            ['report_id' => $reportId, 'patient_id' => 1, 'source_type' => 'appointments', 'source_id' => 11, 'payload' => '{}'],
        ]);
        $this->assertDatabaseCount('dynamic_report_rows', 3);
    }

    public function test_full_generation_counts_appointments_separately_and_patients_once(): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
        $pdo->sqliteCreateFunction('LPAD', fn ($value, $length, $fill) => str_pad((string) $value, (int) $length, (string) $fill, STR_PAD_LEFT));
        Schema::create('dynamic_reports', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status'); $table->integer('progress')->default(0); $table->string('stage')->nullable();
            $table->json('columns'); $table->json('filters'); $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0); $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('dynamic_report_rows', function (Blueprint $table) {
            $table->id(); $table->uuid('report_id'); $table->unsignedBigInteger('patient_id');
            $table->json('payload'); $table->timestamp('created_at')->nullable();
            $table->unique(['report_id', 'patient_id']);
        });
        (require database_path('migrations/tenant/2026_10_17_120000_support_appointment_rows_in_dynamic_reports.php'))->up();
        $patientId = DB::table('patients')->insertGetId([
            'first_name' => 'سارا', 'last_name' => 'محمدی', 'phone' => '09120000000',
            'file_number' => '1001', 'customer_level' => 'problematic',
        ]);
        foreach ([10, 11] as $day) DB::table('appointments')->insert([
            'file_number' => '1001', 'phone' => '09120000000', 'month' => '1405-07',
            'day_num' => $day, 'status' => 'آمد',
        ]);
        DB::table('appointments')->insert([
            'file_number' => '2002', 'phone' => '09350000000', 'lastname' => 'مراجع بدون پرونده',
            'month' => '1405-07', 'day_num' => 12, 'status' => 'وقت داده شد',
        ]);
        DB::table('appointments')->insert([
            'file_number' => '1001', 'phone' => '09120000000', 'month' => '1405-06',
            'day_num' => 12, 'status' => 'آمد',
        ]);
        foreach (['appointments' => 3, 'patients' => 1] as $scope => $expected) {
            $report = DynamicReport::query()->create([
                'columns' => ['fileNo', 'appointmentDate'],
                'filters' => ['scope' => $scope, 'reportDate' => ['from' => '1405-07-01', 'to' => '1405-07-30']],
                'status' => 'queued',
            ]);
            app()->call([new GenerateDynamicReport($report->id), 'handle']);
            $this->assertSame($expected, $report->fresh()->total_rows);
            $this->assertSame($expected, $report->rows()->count());
            $this->assertEqualsCanonicalizing($scope === 'patients' ? [$patientId] : [1, 2, 3],
                $report->rows()->pluck('source_id')->all());
            if ($scope === 'appointments') $this->assertSame(1, $report->rows()->whereNull('patient_id')->count());
        }
    }
}
