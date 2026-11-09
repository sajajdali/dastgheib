<?php

namespace Tests\Unit;

use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use App\Models\FollowupAutomaticSms;
use App\Services\FollowupAutomaticSmsService;
use App\Services\ShsmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/** پیامک اتوماتیک از ستون «پیامک اتوماتیک» پیگیری فعال می‌شود و در نوبت‌دهی فقط خاموش یا روشن می‌شود. */
class FollowupAutomaticSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id(); $table->string('key'); $table->text('value')->nullable(); $table->timestamps();
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
        Schema::create('followup_automatic_sms', function (Blueprint $table) {
            $table->id(); $table->string('enrollment_key')->index(); $table->unsignedBigInteger('campaign_id')->nullable(); $table->string('followup_row_key');
            $table->string('phone'); $table->string('patient_name')->nullable();
            $table->unsignedBigInteger('project_id')->nullable(); $table->unsignedBigInteger('scenario_id')->nullable(); $table->unsignedBigInteger('scenario_step_id')->nullable();
            $table->string('scenario_name')->nullable(); $table->string('inventory_tag')->nullable(); $table->string('sms_template')->nullable();
            $table->unsignedInteger('days_after')->default(0); $table->string('send_at')->nullable(); $table->dateTime('scheduled_at')->nullable();
            $table->boolean('is_enabled')->default(true); $table->string('status')->default('scheduled'); $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable(); $table->dateTime('disabled_at')->nullable(); $table->string('disabled_by_name')->nullable();
            $table->text('last_error')->nullable(); $table->json('provider_response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->string('created_by_name')->nullable(); $table->timestamps();
        });
    }

    public function test_selecting_a_scenario_in_followup_schedules_each_step_once(): void
    {
        $scenario = $this->scenario([3, 9]);
        $service = app(FollowupAutomaticSmsService::class);

        $service->syncRow(1, 'row-1', '09120000000', 'مراجع تست', [$scenario->id], null);
        $service->syncRow(1, 'row-1', '09120000000', 'مراجع تست', [$scenario->id], null);
        // همان شماره در ردیف دیگری از پیگیری دوباره ثبت نمی‌شود.
        $service->syncRow(1, 'row-2', '09120000000', 'مراجع تست', [$scenario->id], null);

        $items = FollowupAutomaticSms::orderBy('days_after')->get();
        $this->assertCount(2, $items);
        $this->assertSame([3, 9], $items->pluck('days_after')->all());
        $this->assertSame(1, $items->pluck('enrollment_key')->unique()->count());
        $this->assertTrue($items->every(fn ($item) => $item->status === 'scheduled' && $item->scheduled_at !== null));
    }

    public function test_disabling_at_booking_is_kept_when_followup_row_is_saved_again(): void
    {
        $scenario = $this->scenario([3, 9]);
        $service = app(FollowupAutomaticSmsService::class);
        $service->syncRow(1, 'row-1', '0912 000 0000', 'مراجع تست', [$scenario->id], null);

        $person = $service->pendingForPhones(collect(['+989120000000']));
        $enrollment = $person->get('09120000000')[0];
        $this->assertSame('active', $enrollment['status']);

        $service->toggle($enrollment['enrollment_key'], false, null);
        $service->syncRow(1, 'row-1', '09120000000', 'مراجع تست', [$scenario->id], null);

        $this->assertSame(2, FollowupAutomaticSms::where('status', 'disabled')->where('is_enabled', false)->count());
        $this->assertSame(0, FollowupAutomaticSms::where('status', 'scheduled')->count());
    }

    public function test_unselecting_a_scenario_cancels_its_pending_messages(): void
    {
        $scenario = $this->scenario([3]);
        $service = app(FollowupAutomaticSmsService::class);
        $service->syncRow(1, 'row-1', '09120000000', null, [$scenario->id], null);
        $service->syncRow(1, 'row-1', '09120000000', null, [], null);

        $this->assertSame('cancelled', FollowupAutomaticSms::firstOrFail()->status);
    }

    public function test_due_message_is_sent_once_and_disabled_one_is_not_sent(): void
    {
        $scenario = $this->scenario([0, 9]);
        $service = app(FollowupAutomaticSmsService::class);
        $service->syncRow(1, 'row-1', '09120000000', 'مراجع تست', [$scenario->id], null);
        $service->syncRow(1, 'row-2', '09130000000', 'مراجع دوم', [$scenario->id], null);
        FollowupAutomaticSms::query()->where('days_after', 0)->update(['scheduled_at' => now()->subMinute()]);
        $second = FollowupAutomaticSms::where('phone', '09130000000')->value('enrollment_key');
        $service->toggle($second, false, null);

        $sms = Mockery::mock(ShsmsService::class);
        $sms->shouldReceive('sendTemplate')->once()->withArgs(fn ($phone, $template, $params) => $phone === '09120000000'
            && $template === 'reminder-0' && $params[0] === 'مراجع تست' && $params[2] === 'ژل لب');

        $this->assertSame(1, $service->sendDueForCurrentTenant($sms)['sent']);
        $this->assertSame(0, $service->sendDueForCurrentTenant($sms)['sent']);
        $this->assertSame('sent', FollowupAutomaticSms::where('phone', '09120000000')->where('days_after', 0)->value('status'));
    }

    private function scenario(array $days): AutomaticSmsScenario
    {
        $project = AutomaticSmsProject::create(['name' => 'فعال', 'is_active' => true]);
        $scenario = AutomaticSmsScenario::create(['project_id' => $project->id, 'name' => 'یادآوری ژل', 'inventory_tag' => 'ژل لب', 'is_active' => true]);
        foreach ($days as $index => $day) {
            $scenario->steps()->create(['sort_order' => $index + 1, 'days_after' => $day, 'send_at' => '10:00', 'sms_template' => "reminder-{$day}"]);
        }

        return $scenario;
    }
}
