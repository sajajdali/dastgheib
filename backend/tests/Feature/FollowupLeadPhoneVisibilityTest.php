<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\FollowupAutoAssignController;
use App\Models\AppSetting;
use App\Models\Campaign;
use App\Models\User;
use App\Support\FollowupLeadPhoneVisibility;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** محدودیت نمایش شمارهٔ لید برای مشاور و تنظیم تقسیم خودکار. */
class FollowupLeadPhoneVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedBigInteger('user_id')->nullable(); $table->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->foreignId('channel_id')->nullable();
            $table->string('starts_on')->nullable(); $table->string('ends_on')->nullable();
            $table->decimal('budget', 15, 2)->default(0); $table->text('note')->nullable();
            $table->boolean('active')->default(true); $table->json('ui_payload')->nullable(); $table->timestamps();
        });
        DB::table('staff')->insert([
            ['id' => 1, 'name' => 'زهره کاظمی', 'user_id' => 8],
            ['id' => 2, 'name' => 'پریا رحمانی', 'user_id' => 9],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('app_settings');
        parent::tearDown();
    }

    /** کاربر آزمایشی با دسترسی دیدن شماره؛ نقش مدیر اختیاری است. */
    private function request(int $userId, string $name, bool $admin = false, bool $canViewPhone = true): Request
    {
        $user = new class extends User {
            public bool $fakeAdmin = false;
            public bool $fakeCanViewPhone = true;
            public function can($abilities, $arguments = []) { return $abilities === 'patients.view_phone' ? $this->fakeCanViewPhone : false; }
            public function hasAnyRole(...$roles): bool { return $this->fakeAdmin; }
        };
        $user->forceFill(['id' => $userId, 'name' => $name]);
        $user->fakeAdmin = $admin;
        $user->fakeCanViewPhone = $canViewPhone;
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function campaign(): Campaign
    {
        return Campaign::query()->create(['name' => 'کمپین', 'ui_payload' => ['rows' => [
            ['_localId' => 'a', 'phone' => '09120000001', 'consultant' => 'زهره کاظمی'],
            ['_localId' => 'b', 'phone' => '09120000002', 'consultant' => 'پریا رحمانی'],
            ['_localId' => 'c', 'phone' => '09120000003', 'consultant' => ''],
        ]]]);
    }

    private function phones(Request $request): array
    {
        $campaigns = app(CampaignController::class)->index($request);

        return collect($campaigns->first()->ui_payload['rows'])->pluck('phone', '_localId')->all();
    }

    public function test_consultant_sees_only_own_lead_phones_when_the_setting_is_on(): void
    {
        $this->campaign();
        AppSetting::query()->create(['key' => FollowupLeadPhoneVisibility::SETTING_KEY, 'value' => '1']);

        // مشاور «زهره کاظمی» (کاربر ۸، متصل به پرسنل با نامی متفاوت از نام کاربری هم کار می‌کند)
        $phones = $this->phones($this->request(8, 'zohre'));
        $this->assertSame('09120000001', $phones['a']);
        $this->assertSame('091••••02', $phones['b']);
        $this->assertSame('091••••03', $phones['c'], 'ردیف بدون مشاور مال هیچ مشاوری نیست.');

        // مدیر سیستم همه را می‌بیند.
        $this->assertSame(['a' => '09120000001', 'b' => '09120000002', 'c' => '09120000003'], $this->phones($this->request(1, 'مدیر کل', true)));
    }

    public function test_all_phones_are_visible_when_the_setting_is_off_and_hidden_without_phone_permission(): void
    {
        $this->campaign();
        $this->assertSame('09120000002', $this->phones($this->request(8, 'zohre'))['b']);
        $this->assertSame('091••••01', $this->phones($this->request(8, 'zohre', false, false))['a']);
    }

    public function test_saving_masked_rows_keeps_the_real_phone_numbers(): void
    {
        $campaign = $this->campaign();
        AppSetting::query()->create(['key' => FollowupLeadPhoneVisibility::SETTING_KEY, 'value' => '1']);
        $request = $this->request(8, 'zohre');
        $seen = app(CampaignController::class)->index($request)->first()->ui_payload['rows'];
        $seen[1]['description'] = 'تماس گرفته شد';

        $update = Request::create('/', 'PUT', ['name' => 'کمپین', 'ui_payload' => ['rows' => $seen], 'base_rows' => app(CampaignController::class)->index($request)->first()->ui_payload['rows']]);
        $update->setUserResolver($request->getUserResolver());
        $response = app(CampaignController::class)->update($update, $campaign);

        $stored = collect($campaign->fresh()->ui_payload['rows'])->keyBy('_localId');
        $this->assertSame('09120000002', $stored['b']['phone']);
        $this->assertSame('تماس گرفته شد', $stored['b']['description']);
        $this->assertSame('09120000003', $stored['c']['phone']);
        // پاسخ ذخیره هم برای همین کاربر ماسک‌شده است.
        $this->assertSame('091••••02', collect($response->ui_payload['rows'])->keyBy('_localId')['b']['phone']);
    }

    public function test_auto_assign_settings_require_a_consultant_and_drop_deleted_staff(): void
    {
        $controller = app(FollowupAutoAssignController::class);
        $this->assertSame(['enabled' => false, 'staff_ids' => []], $controller->show()->getData(true));

        $refused = $controller->update(Request::create('/', 'PUT', ['enabled' => true, 'staff_ids' => []]));
        $this->assertSame(422, $refused->getStatusCode());

        $saved = $controller->update(Request::create('/', 'PUT', ['enabled' => true, 'staff_ids' => [2, 1]]))->getData(true);
        $this->assertSame(['enabled' => true, 'staff_ids' => [2, 1]], $saved);

        DB::table('staff')->where('id', 2)->delete();
        $this->assertSame(['enabled' => true, 'staff_ids' => [1]], $controller->show()->getData(true));
    }
}
