<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AppointmentAutomaticSms;
use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use App\Services\AppointmentAutomaticSmsService;
use App\Services\ShsmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AppointmentAutomaticSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable();
            $table->unsignedTinyInteger('day_num')->nullable();
            $table->string('lastname')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('done')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->string('status')->nullable();
            $table->json('services')->nullable();
            $table->json('service_types')->nullable();
            $table->json('completion_sms_statuses')->nullable();
            $table->json('payment_details')->nullable();
            $table->boolean('new_customer')->default(false);
            $table->timestamps();
        });
        Schema::create('automatic_sms_projects', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('is_active')->default(false); $table->timestamps();
        });
        Schema::create('automatic_sms_scenarios', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->string('name'); $table->string('inventory_tag'); $table->boolean('is_active')->default(false); $table->timestamps();
        });
        Schema::create('automatic_sms_scenario_steps', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('scenario_id'); $table->unsignedInteger('sort_order'); $table->unsignedInteger('days_after'); $table->string('send_at'); $table->string('sms_template')->nullable(); $table->timestamps();
        });
        Schema::create('appointment_automatic_sms', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('appointment_id'); $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('scenario_id')->nullable(); $table->unsignedBigInteger('scenario_step_id')->nullable();
            $table->string('service_name')->nullable(); $table->string('inventory_tag'); $table->string('sms_template')->nullable();
            $table->unsignedInteger('days_after'); $table->string('send_at'); $table->dateTime('scheduled_at')->nullable();
            $table->boolean('is_enabled')->default(true); $table->string('status')->default('waiting_completion');
            $table->unsignedInteger('attempts')->default(0); $table->dateTime('sent_at')->nullable(); $table->dateTime('disabled_at')->nullable();
            $table->text('last_error')->nullable(); $table->json('provider_response')->nullable(); $table->timestamps();
            $table->unique(['appointment_id', 'scenario_step_id']);
        });
    }

    public function test_multi_service_appointment_gets_each_matching_scenario_once(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 2);
        $this->scenario($project, 'بوتاکس پیشانی', 2);

        $appointment = Appointment::create([
            'lastname' => 'بیمار تست',
            'services' => [
                ['name' => 'تزریق ژل', 'tags' => ['ژل لب']],
                ['name' => 'بوتاکس', 'tags' => ['بوتاکس پیشانی']],
                ['name' => 'خدمت تکراری', 'tags' => ['ژل لب']],
            ],
        ]);

        $this->assertSame(4, AppointmentAutomaticSms::where('appointment_id', $appointment->id)->count());
        $this->assertSame(['بوتاکس پیشانی', 'ژل لب'], AppointmentAutomaticSms::where('appointment_id', $appointment->id)->pluck('inventory_tag')->unique()->sort()->values()->all());
    }

    public function test_completion_schedules_steps_and_manual_disable_is_preserved(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 2);
        $appointment = Appointment::create([
            'lastname' => 'بیمار تست', 'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);
        $service = app(AppointmentAutomaticSmsService::class);
        $first = AppointmentAutomaticSms::where('appointment_id', $appointment->id)->firstOrFail();
        $service->toggle($appointment, $first, false);

        $appointment->update(['done' => 'انجام شد', 'completed_at' => now()]);
        $items = AppointmentAutomaticSms::where('appointment_id', $appointment->id)->orderBy('id')->get();

        $this->assertFalse($items->first()->is_enabled);
        $this->assertSame('disabled', $items->first()->status);
        $this->assertSame('scheduled', $items->last()->status);
        $this->assertNotNull($items->last()->scheduled_at);
    }

    public function test_due_message_is_sent_only_once(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 1);
        Appointment::create([
            'lastname' => 'بیمار تست', 'phone' => '09120000000', 'month' => '1405-08', 'day_num' => 1,
            'done' => 'انجام شد', 'completed_at' => now()->subDays(3),
            'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldReceive('sendTemplate')->once();
        $service = app(AppointmentAutomaticSmsService::class);

        $this->assertSame(1, $service->sendDueForCurrentTenant($sms)['sent']);
        $this->assertSame(0, $service->sendDueForCurrentTenant($sms)['sent']);
        $this->assertSame('sent', AppointmentAutomaticSms::firstOrFail()->status);
    }

    public function test_later_visit_for_the_same_service_skips_the_follow_up_even_if_it_was_booked_earlier(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 1);
        $source = Appointment::create([
            'lastname' => 'بیمار تست', 'phone' => '09120000000', 'file_number' => '100',
            'done' => 'انجام شد', 'completed_at' => now()->subDays(3),
            'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);
        $returnVisit = Appointment::create([
            'lastname' => 'بیمار تست', 'phone' => '09120000000', 'file_number' => '100',
            'arrived_at' => now()->subDay(), 'status' => 'آمد',
            'services' => [['name' => 'ژل مجدد', 'tags' => ['ژل لب']]],
        ]);
        DB::table('appointments')->where('id', $returnVisit->id)->update([
            'created_at' => $source->created_at->copy()->subDay(),
        ]);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldNotReceive('sendTemplate');

        $this->assertSame(1, app(AppointmentAutomaticSmsService::class)->sendDueForCurrentTenant($sms)['skipped']);
        $this->assertSame('skipped_returned', AppointmentAutomaticSms::where('appointment_id', $source->id)->firstOrFail()->status);
    }

    public function test_stale_sending_item_is_released_and_retried(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 1);
        Appointment::create([
            'lastname' => 'بیمار تست', 'phone' => '09120000000',
            'done' => 'انجام شد', 'completed_at' => now()->subDays(3),
            'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);
        DB::table('appointment_automatic_sms')->update([
            'status' => 'sending', 'updated_at' => now()->subMinutes(11),
        ]);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldReceive('sendTemplate')->once();

        $this->assertSame(1, app(AppointmentAutomaticSmsService::class)->sendDueForCurrentTenant($sms)['sent']);
        $this->assertSame('sent', AppointmentAutomaticSms::firstOrFail()->status);
    }

    public function test_activating_a_project_does_not_enrol_historical_completed_appointments(): void
    {
        $appointment = Appointment::create([
            'lastname' => 'بیمار قدیمی', 'done' => 'انجام شد', 'completed_at' => now()->subMonths(2),
            'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 1);

        app(AppointmentAutomaticSmsService::class)->resyncAllAppointments();

        $this->assertDatabaseMissing('appointment_automatic_sms', ['appointment_id' => $appointment->id]);
    }

    public function test_reactivating_a_project_does_not_revive_an_expired_cancelled_message(): void
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $this->scenario($project, 'ژل لب', 1);
        $appointment = Appointment::create([
            'lastname' => 'بیمار قدیمی', 'done' => 'انجام شد', 'completed_at' => now()->subDays(3),
            'services' => [['name' => 'ژل', 'tags' => ['ژل لب']]],
        ]);
        $service = app(AppointmentAutomaticSmsService::class);
        $project->update(['is_active' => false]);
        $service->resyncAllAppointments();
        $project->update(['is_active' => true]);
        $service->resyncAllAppointments();

        $item = AppointmentAutomaticSms::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame('cancelled', $item->status);
        $this->assertNull($item->scheduled_at);
    }

    private function scenario(AutomaticSmsProject $project, string $tag, int $steps): AutomaticSmsScenario
    {
        $scenario = AutomaticSmsScenario::create([
            'project_id' => $project->id, 'name' => $tag, 'inventory_tag' => $tag, 'is_active' => true,
        ]);
        foreach (range(1, $steps) as $index) {
            $scenario->steps()->create([
                'sort_order' => $index, 'days_after' => $index, 'send_at' => '10:00', 'sms_template' => 'template_'.$index,
            ]);
        }
        return $scenario;
    }
}
