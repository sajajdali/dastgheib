<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CampaignController;
use App\Models\Campaign;
use App\Models\InventorySection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeneralFollowupSubsectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->string('name');
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('inventory_sections');
        parent::tearDown();
    }

    private function section(string $name, ?int $parentId = null): InventorySection
    {
        $id = DB::table('inventory_sections')->insertGetId([
            'parent_id' => $parentId,
            'level' => $parentId ? 2 : 1,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventorySection::query()->findOrFail($id);
    }

    public function test_subsections_lists_only_leaf_subsections(): void
    {
        $skin = $this->section('پوست و زیبایی');
        $botox = $this->section('بوتاکس', $skin->id);
        $laser = $this->section('لیزر');
        $women = $this->section('لیزر بانوان', $laser->id);
        $face = $this->section('صورت', $women->id);
        $this->section('بدون زیربخش');

        $response = $this->getJson('/api/campaigns/subsections')->assertOk();

        $names = collect($response->json())->pluck('name')->sort()->values()->all();
        $this->assertSame(collect(['بوتاکس', 'صورت'])->sort()->values()->all(), $names);
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($botox->id, $ids);
        $this->assertContains($face->id, $ids);
    }

    public function test_general_followup_row_subsection_is_saved_and_validated(): void
    {
        $skin = $this->section('پوست و زیبایی');
        $botox = $this->section('بوتاکس', $skin->id);

        $campaign = $this->postJson('/api/campaigns/general')->assertOk()->json();

        $payload = [
            'name' => 'پیگیری کلی',
            'note' => '__system_general_followups__',
            'active' => true,
            'ui_payload' => [
                'title' => 'پیگیری کلی',
                'isGeneralFollowup' => true,
                'rows' => [
                    ['_localId' => 'a', 'fullName' => 'مریم', 'contactDate' => '2026-11-05', 'subsectionId' => $botox->id, 'subsectionName' => 'بوتاکس'],
                    ['_localId' => 'b', 'fullName' => 'سارا', 'subsectionId' => null, 'subsectionName' => ''],
                ],
            ],
        ];

        $controller = app(CampaignController::class);
        $model = Campaign::query()->findOrFail($campaign['id']);
        $saved = $controller->update(Request::create('/', 'PUT', $payload), $model)->toArray();

        $this->assertSame($botox->id, $saved['ui_payload']['rows'][0]['subsectionId']);
        $this->assertSame('بوتاکس', $saved['ui_payload']['rows'][0]['subsectionName']);
        $this->assertSame('مریم', $saved['ui_payload']['rows'][0]['fullName']);
        $this->assertSame('2026-11-05', $saved['ui_payload']['rows'][0]['contactDate']);
        $this->assertTrue($saved['ui_payload']['isGeneralFollowup']);

        $this->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonPath('0.ui_payload.rows.0.subsectionId', $botox->id)
            ->assertJsonPath('0.ui_payload.rows.1.fullName', 'سارا');

        $payload['ui_payload']['rows'][0]['subsectionId'] = 'not-a-number';
        try {
            $controller->update(Request::create('/', 'PUT', $payload), $model->fresh());
            $this->fail('subsectionId نامعتبر باید رد شود.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ui_payload.rows.0.subsectionId', $exception->errors());
        }
    }
}
