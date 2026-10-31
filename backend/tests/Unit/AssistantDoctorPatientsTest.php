<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class AssistantDoctorPatientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('doctors');
        Mockery::close();
        parent::tearDown();
    }

    public function test_preview_counts_distinct_patients_and_uses_same_doctor_filter_as_report_builder(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('file_number')->nullable();
            $table->string('phone')->nullable();
            $table->softDeletes();
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('doctor');
            $table->string('file_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('month');
            $table->integer('day_num');
        });
        DB::table('doctors')->insert([['name'=>'دکتر احمدی'], ['name'=>'دکتر موسوی']]);
        DB::table('patients')->insert([
            ['first_name'=>'علی','last_name'=>'موسوی','file_number'=>'101','phone'=>'09111111111'],
            ['first_name'=>'سارا','last_name'=>'نوری','file_number'=>'102','phone'=>'09222222222'],
        ]);
        DB::table('appointments')->insert([
            ['doctor'=>'دکتر احمدی','file_number'=>'101','month'=>'1405-08','day_num'=>1],
            ['doctor'=>'دکتر احمدی','file_number'=>'101','month'=>'1405-08','day_num'=>2],
            ['doctor'=>'دکتر موسوی','file_number'=>'102','month'=>'1405-08','day_num'=>2],
            ['doctor'=>'دکتر احمدی','file_number'=>'102','month'=>'1405-07','day_num'=>30],
        ]);
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['patients.view','reports.view'], true));

        $result = app(AssistantToolExecutor::class)->execute($user, 'preview_doctor_patients', [
            'doctor'=>'احمدی','period'=>'custom','from'=>'1405-08-01','to'=>'1405-08-30',
        ]);

        $this->assertSame(1, $result['model']['count']);
        $this->assertSame('علی موسوی', $result['model']['rows'][0]['name']);
        $this->assertSame('101', $result['presentation']['row_actions'][0]['file_number']);
        $this->assertSame('report_builder', $result['presentation']['page']);
        $this->assertSame('دکتر احمدی', $result['presentation']['report']['doctor']);

        $person = app(AssistantToolExecutor::class)->execute($user, 'search_people', ['query'=>'علی موسوی']);
        $this->assertSame(1, $person['model']['count']);
        $this->assertSame('علی موسوی', $person['model']['rows'][0]['name']);
        $this->assertSame('patient_profile', $person['presentation']['row_actions'][0]['page']);
    }
}
