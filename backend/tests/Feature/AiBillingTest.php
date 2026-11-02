<?php

namespace Tests\Feature;

use App\Services\Assistant\Billing\AiBalanceException;
use App\Services\Assistant\Billing\AiMeter;
use App\Services\Assistant\Billing\AiPriceMissingException;
use App\Services\Assistant\Billing\AiUsageContext;
use App\Services\Assistant\Billing\AiUsageReport;
use App\Services\Assistant\Billing\AiWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class AiBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.api_key' => 'test-secret', 'assistant.transcription_model' => 'gpt-transcribe']);
        foreach (['clinic-a', 'clinic-b'] as $id) {
            DB::table('tenants')->insert(['id' => $id, 'data' => json_encode(['name' => $id]), 'created_at' => now(), 'updated_at' => now()]);
        }
        // ۲ دلار برای هر میلیون توکن ورودی، ۰٫۵ دلار برای cache و ۸ دلار برای خروجی.
        $this->price('gpt-6-luna', ['input_per_m' => 2000000, 'cached_input_per_m' => 500000, 'output_per_m' => 8000000]);
    }

    public function test_actual_usage_is_charged_with_markup_and_hold_is_released(): void
    {
        $wallet = app(AiWallet::class);
        $wallet->charge('clinic-a', 5000000, null, null, null, null);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response(10000, 4000, 1000, 200), 200)]);

        app(AiMeter::class)->respond($this->context('clinic-a'), ['model' => 'gpt-6-luna', 'input' => 'سلام', 'max_output_tokens' => 1600]);

        // هزینه: ۶۰۰۰×۲ + ۴۰۰۰×۰٫۵ + ۱۰۰۰×۸ = ۲۲۰۰۰ میکرودلار؛ با ضریب پیش‌فرض ۱٫۳ می‌شود ۲۸۶۰۰.
        $this->assertDatabaseHas('central_ai_usage', [
            'tenant_id' => 'clinic-a', 'type' => 'chat', 'input_tokens' => 10000, 'cached_input_tokens' => 4000,
            'output_tokens' => 1000, 'reasoning_tokens' => 200, 'cost_micros' => 22000, 'charge_micros' => 28600,
            'uncollected_micros' => 0, 'response_id' => 'resp_1', 'estimated' => false,
        ]);
        $state = $wallet->state('clinic-a');
        $this->assertSame(5000000 - 28600, $state['balance_micros']);
        $this->assertSame(0, $state['held_micros']);
        $this->assertDatabaseHas('central_ai_wallet_transactions', ['tenant_id' => 'clinic-a', 'type' => 'usage', 'amount_micros' => -28600]);
    }

    public function test_per_tenant_multiplier_overrides_global_one(): void
    {
        app(AiWallet::class)->charge('clinic-b', 5000000, null, null, null, null);
        DB::table('central_ai_wallets')->where('tenant_id', 'clinic-b')->update(['markup_multiplier' => 2]);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response(1000, 0, 0, 0), 200)]);

        app(AiMeter::class)->respond($this->context('clinic-b'), ['model' => 'gpt-6-luna', 'input' => 'سلام']);

        $this->assertDatabaseHas('central_ai_usage', ['tenant_id' => 'clinic-b', 'cost_micros' => 2000, 'charge_micros' => 4000, 'markup_multiplier' => 2]);
    }

    public function test_request_is_refused_before_calling_openai_when_its_maximum_cost_is_not_covered(): void
    {
        app(AiWallet::class)->charge('clinic-a', 5000, null, null, null, null);
        Http::fake();

        try {
            app(AiMeter::class)->respond($this->context('clinic-a'), ['model' => 'gpt-6-luna', 'input' => 'سلام']);
            $this->fail('درخواست باید رد می‌شد.');
        } catch (AiBalanceException $exception) {
            $this->assertSame('insufficient_for_request', $exception->reason);
        }
        Http::assertNothingSent();
        $this->assertSame(5000, app(AiWallet::class)->state('clinic-a')['balance_micros']);
    }

    public function test_balance_below_one_dollar_blocks_usage_with_clear_persian_message(): void
    {
        app(AiWallet::class)->charge('clinic-a', 730000, null, null, null, null);

        try {
            app(AiWallet::class)->ensureUsable('clinic-a');
            $this->fail('باید قفل می‌شد.');
        } catch (AiBalanceException $exception) {
            $this->assertStringContainsString('هم‌اکنون ۰٫۷۳ دلار است (کمتر از ۱٫۰۰ دلار) و امکان استفاده ندارید', $exception->messageFor(true));
            $this->assertStringContainsString('به مدیر کلینیک اطلاع دهید', $exception->messageFor(false));
        }
    }

    public function test_failed_openai_call_costs_nothing_and_releases_hold(): void
    {
        app(AiWallet::class)->charge('clinic-a', 5000000, null, null, null, null);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['type' => 'server_error']], 500)]);

        try {
            app(AiMeter::class)->respond($this->context('clinic-a'), ['model' => 'gpt-6-luna', 'input' => 'سلام']);
        } catch (RuntimeException) {
        }

        $state = app(AiWallet::class)->state('clinic-a');
        $this->assertSame(5000000, $state['balance_micros']);
        $this->assertSame(0, $state['held_micros']);
        $this->assertDatabaseCount('central_ai_usage', 0);
    }

    public function test_model_without_price_is_not_usable(): void
    {
        app(AiWallet::class)->charge('clinic-a', 5000000, null, null, null, null);
        Http::fake();

        $this->expectException(AiPriceMissingException::class);
        app(AiMeter::class)->respond($this->context('clinic-a'), ['model' => 'gpt-6.1-sol', 'input' => 'سلام']);
    }

    public function test_transcription_is_charged_by_tokens_or_by_duration(): void
    {
        app(AiWallet::class)->charge('clinic-a', 5000000, null, null, null, null);
        $this->price('gpt-transcribe', ['input_per_m' => 1000000, 'audio_input_per_m' => 3000000, 'output_per_m' => 4000000]);
        Http::fake(['api.openai.com/v1/audio/transcriptions' => Http::sequence()
            ->push(['text' => 'امروز چند نوبت داریم؟', 'usage' => ['type' => 'tokens', 'input_tokens' => 1100, 'input_token_details' => ['audio_tokens' => 1000, 'text_tokens' => 100], 'output_tokens' => 50]], 200)
            ->push(['text' => 'سلام', 'usage' => ['type' => 'duration', 'seconds' => 30]], 200)]);

        $text = app(AiMeter::class)->transcribe($this->context('clinic-a', 'transcription'), UploadedFile::fake()->create('voice.webm', 24, 'audio/webm'));

        $this->assertSame('امروز چند نوبت داریم؟', $text);
        // ۱۰۰۰×۳ + ۱۰۰×۱ + ۵۰×۴ = ۳۳۰۰ میکرودلار؛ با ضریب ۱٫۳ = ۴۲۹۰.
        $this->assertDatabaseHas('central_ai_usage', ['type' => 'transcription', 'audio_input_tokens' => 1000, 'input_tokens' => 1100, 'output_tokens' => 50, 'cost_micros' => 3300, 'charge_micros' => 4290]);

        $this->price('gpt-transcribe', ['input_per_m' => 0, 'output_per_m' => 0, 'per_minute' => 6000]);
        app(AiMeter::class)->transcribe($this->context('clinic-a', 'transcription'), UploadedFile::fake()->create('voice.webm', 24, 'audio/webm'));
        $this->assertDatabaseHas('central_ai_usage', ['type' => 'transcription', 'audio_seconds' => 30, 'cost_micros' => 3000, 'charge_micros' => 3900]);
    }

    public function test_per_minute_price_is_not_zeroed_when_response_only_reports_tokens(): void
    {
        app(AiWallet::class)->charge('clinic-a', 5000000, null, null, null, null);
        $this->price('gpt-transcribe', ['input_per_m' => 0, 'output_per_m' => 0, 'per_minute' => 6000]);
        Http::fake(['api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'سلام', 'usage' => ['type' => 'tokens', 'input_tokens' => 500, 'output_tokens' => 20]], 200)]);

        app(AiMeter::class)->transcribe($this->context('clinic-a', 'transcription'), UploadedFile::fake()->create('voice.webm', 24, 'audio/webm'));

        // بدون مدت صوت، سقف ۹۰ ثانیه ملاک است: ۹۰ × ۶۰۰۰ / ۶۰ = ۹۰۰۰ میکرودلار.
        $this->assertDatabaseHas('central_ai_usage', ['type' => 'transcription', 'audio_seconds' => 90, 'cost_micros' => 9000, 'estimated' => true]);
    }

    public function test_negative_adjustment_cannot_make_balance_negative(): void
    {
        $wallet = app(AiWallet::class);
        $wallet->charge('clinic-a', 1000000, null, null, null, null);

        $this->expectException(InvalidArgumentException::class);
        $wallet->adjust('clinic-a', -1000001, null, 'اصلاح آزمایشی');
    }

    public function test_report_groups_usage_and_scopes_it_to_one_user(): void
    {
        app(AiWallet::class)->charge('clinic-a', 50000000, null, null, null, null);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->response(1000, 0, 100, 0), 200)]);
        app(AiMeter::class)->respond($this->context('clinic-a', 'chat', 1, 'مریم'), ['model' => 'gpt-6-luna', 'input' => 'الف']);
        app(AiMeter::class)->respond($this->context('clinic-a', 'chat', 1, 'مریم'), ['model' => 'gpt-6-luna', 'input' => 'ب']);
        app(AiMeter::class)->respond($this->context('clinic-a', 'chat', 2, 'رضا'), ['model' => 'gpt-6-luna', 'input' => 'ج']);

        $report = app(AiUsageReport::class);
        $byUser = $report->grouped(['tenant_id' => 'clinic-a'], 'user', 'model');
        $this->assertCount(2, $byUser['rows']);
        $this->assertSame('مریم', $byUser['rows'][0]['label']);
        $this->assertSame(2, $byUser['rows'][0]['questions']);
        $this->assertSame(3, $byUser['totals']['calls']);
        $this->assertArrayNotHasKey('cost_usd', $byUser['totals']);
        $this->assertArrayHasKey('profit_usd', AiUsageReport::central()->totals(['tenant_id' => 'clinic-a']));

        $periods = collect($report->periods(['tenant_id' => 'clinic-a', 'user_id' => 2]))->keyBy('key');
        $this->assertSame(1, $periods['today']['calls']);
        $this->assertSame(1000, $periods['this_month']['input_tokens']);
        $this->assertSame(0, $periods['yesterday']['calls']);
    }

    private function price(string $model, array $rates): void
    {
        DB::table('central_ai_model_prices')->insert([
            'model' => $model, 'kind' => $model === 'gpt-transcribe' ? 'transcription' : 'chat',
            'input_per_m' => 0, 'output_per_m' => 0, ...$rates,
            'effective_from' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function context(string $tenant, string $type = 'chat', int $userId = 1, string $name = 'کاربر'): AiUsageContext
    {
        return new AiUsageContext($tenant, $userId, $name, $type, null, (string) Str::uuid());
    }

    private function response(int $input, int $cached, int $output, int $reasoning): array
    {
        return [
            'id' => 'resp_1',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'پاسخ']]]],
            'usage' => [
                'input_tokens' => $input, 'input_tokens_details' => ['cached_tokens' => $cached],
                'output_tokens' => $output, 'output_tokens_details' => ['reasoning_tokens' => $reasoning],
                'total_tokens' => $input + $output,
            ],
        ];
    }
}
