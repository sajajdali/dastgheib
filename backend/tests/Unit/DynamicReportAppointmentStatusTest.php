<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportChunkContextLoader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicReportAppointmentStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('customer_level')->nullable();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('referrer_phone')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        parent::tearDown();
    }

    public function test_status_column_uses_latest_appointment_matching_selected_statuses(): void
    {
        $patientId = DB::table('patients')->insertGetId([
            'first_name' => 'سارا',
            'phone' => '09120000000',
            'file_number' => '1001',
            'customer_level' => 'problematic',
        ]);
        $patient = Patient::query()->findOrFail($patientId);

        $matchingId = DB::table('appointments')->insertGetId([
            'phone' => $patient->phone,
            'file_number' => $patient->file_number,
            'status' => 'آمد',
        ]);
        $newerUnrelatedId = DB::table('appointments')->insertGetId([
            'phone' => $patient->phone,
            'file_number' => $patient->file_number,
            'status' => 'وقت داده شد',
        ]);

        $context = app(DynamicReportChunkContextLoader::class)
            ->load(collect([$patient]), ['multi' => ['status' => ['آمد']]])
            ->first();

        $this->assertSame($newerUnrelatedId, $context->appointmentHistory->first()->id);
        $this->assertSame($matchingId, $context->latestAppointment->id);
        $this->assertSame('آمد', $context->latestAppointment->status);
    }
}
