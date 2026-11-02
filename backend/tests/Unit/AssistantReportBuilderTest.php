<?php

namespace Tests\Unit;

use App\Models\User;
use App\Reporting\DynamicReports\DynamicReportFilterPipeline;
use App\Services\Assistant\AssistantReportBuilder;
use App\Services\Assistant\AssistantToolExecutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class AssistantReportBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            foreach (['first_name', 'last_name', 'phone', 'national_id', 'file_number', 'gender', 'birth_date', 'city', 'financial_status', 'customer_level'] as $column) {
                $table->string($column)->nullable();
            }
            $table->softDeletes();
        });
        // موتور گزارش برای MySQL نوشته شده؛ این دو تابع را برای SQLite حافظه‌ای شبیه‌سازی می‌کنیم.
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
        $pdo->sqliteCreateFunction('LPAD', fn ($value, $length, $pad) => str_pad((string) $value, (int) $length, (string) $pad, STR_PAD_LEFT));
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            foreach (['file_number', 'phone', 'month', 'day_num', 'status', 'doctor', 'referrer_phone', 'services', 'amount', 'debt', 'lastname', 'gender', 'done'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
        DB::table('patients')->insert([
            ['first_name' => 'علی', 'last_name' => 'محمدی', 'phone' => '09121111111', 'national_id' => '0011111111', 'file_number' => '1'],
            ['first_name' => 'سارا', 'last_name' => 'نیک‌محمدی', 'phone' => '09122222222', 'national_id' => null, 'file_number' => '2'],
            ['first_name' => 'رضا', 'last_name' => 'کریمی', 'phone' => '09123333333', 'national_id' => '', 'file_number' => '3'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_arguments_become_an_exact_report_builder_payload(): void
    {
        $payload = app(AssistantReportBuilder::class)->payload([
            'scope' => 'patients', 'columns' => ['name', 'family', 'phone', 'unknown'],
            'text_filters' => [['field' => 'family', 'mode' => 'contains', 'value' => 'محمدی'], ['field' => 'nationalId', 'mode' => 'has_value', 'value' => '']],
            'gender' => 'female', 'no_return_months' => 3,
        ]);

        $this->assertSame(['name', 'family', 'phone'], $payload['columns']);
        $this->assertSame('*محمدی', $payload['filters']['values']['family']);
        $this->assertSame('*', $payload['filters']['values']['nationalId']);
        $this->assertSame('زن', $payload['filters']['values']['gender']);
        $this->assertSame('۳ ماه', $payload['filters']['values']['noreturn']);
        $this->assertSame([1300, 1, 1], $payload['filters']['reportDate']['from']);
    }

    public function test_text_markers_match_contains_has_value_and_empty(): void
    {
        $pipeline = app(DynamicReportFilterPipeline::class);
        $files = fn (array $values) => $pipeline->query(['scope' => 'patients', 'values' => $values])->pluck('file_number')->all();

        $this->assertSame(['1', '2'], $files(['family' => '*محمدی']));
        $this->assertSame(['1'], $files(['family' => 'محمدی']));
        $this->assertSame(['1'], $files(['nationalId' => '*']));
        $this->assertSame(['2', '3'], $files(['nationalId' => '-']));
    }

    public function test_build_report_tool_lists_mobile_numbers_and_offers_report_builder(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'reports.view');

        $result = app(AssistantToolExecutor::class)->execute($user, 'build_report', [
            'scope' => 'patients', 'columns' => ['name', 'family', 'phone'],
            'text_filters' => [['field' => 'family', 'mode' => 'contains', 'value' => 'محمدی']], 'title' => 'محمدی‌ها با موبایل',
        ]);

        $this->assertSame(2, $result['model']['count']);
        $this->assertSame(['name' => 'نام', 'family' => 'نام خانوادگی', 'phone' => 'شماره تماس'], $result['presentation']['columns']);
        $this->assertSame('09121111111', $result['presentation']['rows'][0]['phone']);
        $this->assertSame('*محمدی', $result['presentation']['report']['filters']['values']['family']);
        $this->assertSame('1', $result['presentation']['row_actions'][0]['file_number']);
    }

    public function test_metric_cards_offer_report_builder_only_to_users_who_can_open_it(): void
    {
        $user = fn (array $permissions) => tap(Mockery::mock(User::class), fn ($mock) => $mock->shouldReceive('can')
            ->andReturnUsing(fn (string $permission) => in_array($permission, $permissions, true)));
        $executor = app(AssistantToolExecutor::class);

        $without = $executor->execute($user(['patients.view']), 'count_patients', []);
        $with = $executor->execute($user(['patients.view', 'reports.view']), 'count_patients', []);

        $this->assertArrayNotHasKey('report', $without['presentation']);
        $this->assertSame('patients', $with['presentation']['report']['filters']['scope']);
        $this->assertSame(3, $with['presentation']['value']);
    }

    public function test_invalid_filter_value_returns_allowed_values_and_close_values_are_resolved(): void
    {
        $builder = app(AssistantReportBuilder::class);
        $payload = $builder->payload(['scope' => 'appointments', 'multi_filters' => [['field' => 'status', 'values' => ['آمده']]]]);
        $this->assertSame(['آمد'], $payload['filters']['multi']['status']);

        try {
            $builder->payload(['scope' => 'appointments', 'multi_filters' => [['field' => 'finstatus', 'values' => ['تسویه‌شده']]]]);
            $this->fail('مقدار نامعتبر باید رد می‌شد.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('مقادیر مجاز: ضعیف، متوسط، خوب، عالی', $exception->getMessage());
            $this->assertStringContainsString('نه پرداخت نوبت', $exception->getMessage());
        }
    }

    public function test_newest_sort_puts_latest_records_first(): void
    {
        $builder = app(AssistantReportBuilder::class);
        $payload = $builder->payload(['scope' => 'patients', 'columns' => ['fileNo'], 'sort' => 'newest']);

        $this->assertSame('desc', $payload['filters']['sort']);
        $this->assertSame('3', $builder->preview($payload, 0, 1)['rows'][0]['fileNo']);
    }

    public function test_help_catalog_answers_where_a_feature_is(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturn(false);

        $result = app(\App\Services\Assistant\AssistantHelpCatalog::class)->search($user, 'کجا پزشک اضافه کنم');

        $this->assertSame('پزشکان', $result['model']['matches'][0]['title']);
        $this->assertSame('تنظیمات ← منابع ← پزشک', $result['presentation']['fields']['کجاست']);
        $this->assertNull($result['presentation']['action']);
    }

    public function test_help_catalog_ignores_short_filler_words(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturn(false);
        $help = app(\App\Services\Assistant\AssistantHelpCatalog::class);

        $this->assertSame('دسترسی‌های من', $help->search($user, 'چرا منو رو نمی‌بینم')['model']['matches'][0]['title']);
        $this->assertSame('تعریف کاربر', $help->search($user, 'کاربر جدید چطور تعریف کنم')['model']['matches'][0]['title']);
    }
}
