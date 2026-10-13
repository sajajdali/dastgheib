<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PatientController;
use App\Models\AppSetting;
use App\Models\Patient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PatientFileNumberUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('second_phone')->nullable();
            $table->string('file_number')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_manual_number_change_updates_patient_history(): void
    {
        AppSetting::create(['key' => 'appointment_file_number_locked', 'value' => '0']);
        $patient = Patient::create(['first_name' => 'احسان', 'phone' => '09120000001', 'file_number' => '100']);
        DB::table('appointments')->insert(['file_number' => '100']);
        DB::table('services')->insert(['file_number' => '100']);

        $response = app(PatientController::class)->update(Request::create('/api/patients/'.$patient->id, 'PUT', [
            'file_number' => '۱۰۱',
        ]), $patient->id);

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('101', $patient->fresh()->file_number);
        $this->assertDatabaseHas('appointments', ['file_number' => '101']);
        $this->assertDatabaseHas('services', ['file_number' => '101']);
    }

    public function test_locked_number_cannot_be_changed(): void
    {
        AppSetting::create(['key' => 'appointment_file_number_locked', 'value' => '1']);
        $patient = Patient::create(['first_name' => 'احسان', 'phone' => '09120000001', 'file_number' => '100']);

        try {
            app(PatientController::class)->update(Request::create('/api/patients/'.$patient->id, 'PUT', [
                'file_number' => '101',
            ]), $patient->id);
            $this->fail('Changing a locked file number should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file_number', $exception->errors());
        }

        $this->assertSame('100', $patient->fresh()->file_number);
    }

    public function test_duplicate_number_cannot_be_used(): void
    {
        AppSetting::create(['key' => 'appointment_file_number_locked', 'value' => '0']);
        $patient = Patient::create(['first_name' => 'احسان', 'phone' => '09120000001', 'file_number' => '100']);
        Patient::create(['first_name' => 'مریم', 'phone' => '09120000002', 'file_number' => '101']);

        try {
            app(PatientController::class)->update(Request::create('/api/patients/'.$patient->id, 'PUT', [
                'file_number' => '101',
            ]), $patient->id);
            $this->fail('A duplicate file number should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file_number', $exception->errors());
        }

        $this->assertSame('100', $patient->fresh()->file_number);
    }

    public function test_soft_delete_hides_patient_but_preserves_history_and_reserved_number(): void
    {
        $patient = Patient::create(['first_name' => 'احسان', 'phone' => '09120000001', 'file_number' => '100']);
        DB::table('appointments')->insert(['file_number' => '100']);

        app(PatientController::class)->destroy($patient);

        $this->assertNull(Patient::find($patient->id));
        $this->assertNotNull(Patient::withTrashed()->find($patient->id));
        $this->assertDatabaseHas('appointments', ['file_number' => '100']);
        $this->assertTrue(app(PatientController::class)->checkDuplicate(Request::create('/api/patients/check-duplicate', 'GET', [
            'file_number' => '100',
        ]))->getData(true)['file_number_exists']);
        $this->assertSame('101', app(PatientController::class)->nextFileNumber()->getData(true)['file_number']);
    }
}
