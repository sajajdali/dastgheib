<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CampaignController;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\User;
use App\Services\CampaignLeadSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** فهرست سبک کمپین + صفحه‌بندی سرنخ‌ها برای دادهٔ حجیم پیگیری. */
class CampaignLeadsPaginationTest extends TestCase
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('channel_id')->nullable();
            $table->string('starts_on')->nullable();
            $table->string('ends_on')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->json('ui_payload')->nullable();
            $table->timestamps();
        });
        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('local_id', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('full_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('consultant')->nullable();
            $table->string('source')->nullable();
            $table->string('status', 40)->nullable();
            $table->string('interest', 20)->nullable();
            $table->string('reason')->nullable();
            $table->string('contact_date', 16)->nullable();
            $table->string('contact_time', 16)->nullable();
            $table->string('follow_up_date', 16)->nullable();
            $table->unsignedBigInteger('subsection_id')->nullable();
            $table->string('subsection_name')->nullable();
            $table->boolean('appointment_registered')->default(false);
            $table->boolean('appointment_done')->default(false);
            $table->boolean('meaningful')->default(false);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'local_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaign_leads');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('app_settings');
        parent::tearDown();
    }

    private function request(array $query = [], string $method = 'GET'): Request
    {
        $user = new class extends User {
            public function can($abilities, $arguments = [])
            {
                return $abilities === 'patients.view_phone';
            }

            public function hasAnyRole(...$roles): bool
            {
                return true;
            }
        };
        $user->forceFill(['id' => 1, 'name' => 'مدیر']);
        $request = Request::create('/', $method, $query);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedHeavyCampaign(int $rows = 120): Campaign
    {
        $today = now()->toDateString();
        $payloadRows = [];
        for ($i = 1; $i <= $rows; $i++) {
            $payloadRows[] = [
                '_localId' => "row-{$i}",
                'fullName' => "مراجع {$i}",
                'phone' => sprintf('0912%07d', $i),
                'status' => $i % 5 === 0 ? 'پاسخ داد' : '',
                'interest' => $i % 7 === 0 ? 'ok' : '',
                'followUpDate' => $i <= 3 ? $today : ($i <= 6 ? '2020-01-01' : ''),
                'consultant' => 'سارا',
            ];
        }

        $campaign = Campaign::query()->create([
            'name' => 'کمپین سنگین',
            'active' => true,
            'ui_payload' => [
                'title' => 'کمپین سنگین',
                'campaignStatus' => 'active',
                'rows' => $payloadRows,
            ],
        ]);

        app(CampaignLeadSync::class)->replaceAll($campaign, $payloadRows);

        return $campaign;
    }

    public function test_index_omits_rows_and_exposes_lead_stats(): void
    {
        $this->seedHeavyCampaign(80);
        $list = app(CampaignController::class)->index($this->request());
        $campaign = $list->first();

        $this->assertTrue((bool) $campaign->rows_omitted);
        $this->assertArrayNotHasKey('rows', $campaign->ui_payload ?? []);
        $this->assertSame(80, $campaign->lead_stats['total']);
        $this->assertGreaterThan(0, $campaign->lead_stats['today']);
        $this->assertGreaterThan(0, $campaign->lead_stats['missed']);
    }

    public function test_leads_endpoint_paginates_and_filters(): void
    {
        $campaign = $this->seedHeavyCampaign(120);
        $page1 = app(CampaignController::class)
            ->leads($this->request(['page' => 1, 'per_page' => 50]), $campaign)
            ->getData(true);

        $this->assertCount(50, $page1['data']);
        $this->assertSame(120, $page1['meta']['total']);
        $this->assertTrue($page1['meta']['has_more']);
        $this->assertSame('row-1', $page1['data'][0]['_localId']);

        $page3 = app(CampaignController::class)
            ->leads($this->request(['page' => 3, 'per_page' => 50]), $campaign)
            ->getData(true);
        $this->assertCount(20, $page3['data']);
        $this->assertFalse($page3['meta']['has_more']);

        $filtered = app(CampaignController::class)
            ->leads($this->request(['page' => 1, 'per_page' => 50, 'q' => 'مراجع 12']), $campaign)
            ->getData(true);
        $this->assertNotEmpty($filtered['data']);
        $this->assertTrue(collect($filtered['data'])->every(
            fn ($row) => str_contains((string) $row['fullName'], '12') || str_contains((string) $row['phone'], '12')
        ));
    }

    public function test_sync_leads_updates_without_full_history_payload(): void
    {
        $campaign = $this->seedHeavyCampaign(30);
        $request = $this->request([
            'upserts' => [[
                '_localId' => 'row-1',
                'fullName' => 'مراجع ویرایش‌شده',
                'phone' => '09120000001',
                'status' => 'پاسخ داد',
                'consultant' => 'سارا',
            ]],
            'deletes' => ['row-2'],
        ], 'POST');

        $response = app(CampaignController::class)->syncLeads($request, $campaign)->getData(true);
        $this->assertSame(29, $response['lead_stats']['total']);
        $this->assertSame('مراجع ویرایش‌شده', CampaignLead::query()->where('local_id', 'row-1')->value('full_name'));
        $this->assertFalse(CampaignLead::query()->where('local_id', 'row-2')->exists());
    }

    public function test_alerts_endpoint_returns_lean_today_and_missed(): void
    {
        $this->seedHeavyCampaign(10);
        $alerts = app(CampaignController::class)->alerts($this->request())->getData(true);
        $this->assertNotEmpty($alerts['today']);
        $this->assertNotEmpty($alerts['missed']);
        $this->assertArrayHasKey('campaignId', $alerts['today'][0]);
        $this->assertArrayNotHasKey('notes', $alerts['today'][0]);
    }
}
