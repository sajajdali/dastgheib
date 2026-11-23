<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Campaign;
use App\Services\ClinicReportService;
use App\Services\CustomerLevelService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClinicReportKpiDrilldownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable();
            $table->integer('day_num')->nullable();
            $table->string('lastname')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('time')->nullable();
            $table->string('description')->nullable();
            $table->string('status')->nullable();
            $table->string('done')->nullable();
            $table->string('amount')->nullable();
            $table->string('wallet_applied')->nullable();
            $table->string('debt')->nullable();
            $table->string('discount')->nullable();
            $table->json('services')->nullable();
            $table->json('payment_details')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->boolean('new_customer')->default(false);
            $table->string('doctor')->nullable();
            $table->string('consultant')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->string('starts_on')->nullable();
            $table->string('ends_on')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->json('ui_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('type', 30);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    private function reports(): ClinicReportService
    {
        return new ClinicReportService(app(CustomerLevelService::class));
    }

    private function filters(): array
    {
        return $this->reports()->normalizeFilters([
            'from' => '1404-08-01',
            'to' => '1404-08-31',
        ]);
    }

    public function test_receivables_drilldown_lists_debtors_and_matches_total(): void
    {
        Appointment::query()->create([
            'month' => '1404-08',
            'day_num' => 10,
            'lastname' => 'بدهکار یک',
            'phone' => '09120000001',
            'time' => '10:00',
            'status' => 'آمد',
            'done' => 'انجام شد',
            'amount' => 1000000,
            'debt' => 250000,
            'discount' => 0,
            'services' => [['name' => 'بوتاکس', 'price' => 1000000, 'quantity' => 1]],
        ]);
        Appointment::query()->create([
            'month' => '1404-08',
            'day_num' => 11,
            'lastname' => 'بدون بدهی',
            'phone' => '09120000002',
            'status' => 'آمد',
            'done' => 'انجام شد',
            'amount' => 800000,
            'debt' => 0,
            'discount' => 0,
            'payment_details' => ['ledger_total' => 800000],
            'services' => [['name' => 'فیلر', 'price' => 800000, 'quantity' => 1]],
        ]);

        $result = $this->reports()->drilldown('receivables', $this->filters());

        $this->assertSame('receivables', $result['metric']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('بدهکار یک', $result['rows'][0]['patient']);
        $this->assertSame('09120000001', $result['rows'][0]['phone']);
        $this->assertSame('1404-08-10', $result['rows'][0]['debt_date']);
        $this->assertSame(250000.0, $result['rows'][0]['amount']);
        $this->assertSame('بوتاکس', $result['rows'][0]['service']);
        $this->assertSame(250000.0, $result['total']);
    }

    public function test_advertising_drilldown_lists_campaign_costs(): void
    {
        Campaign::query()->create([
            'name' => 'کمپین اینستا',
            'budget' => 1500000,
            'starts_on' => '1404-08-05',
            'ui_payload' => ['cost' => 1500000, 'date' => '1404/08/05', 'source' => 'اینستاگرام'],
        ]);
        Campaign::query()->create([
            'name' => 'خارج از بازه',
            'budget' => 900000,
            'starts_on' => '1404-07-01',
            'ui_payload' => ['cost' => 900000, 'date' => '1404/07/01'],
        ]);

        $result = $this->reports()->drilldown('advertising', $this->filters());

        $this->assertSame('advertising', $result['metric']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('کمپین اینستا', $result['rows'][0]['name']);
        $this->assertSame(1500000.0, $result['rows'][0]['amount']);
        $this->assertSame(1500000.0, $result['total']);
    }

    public function test_discount_drilldown_lists_completed_discounts(): void
    {
        Appointment::query()->create([
            'month' => '1404-08',
            'day_num' => 12,
            'lastname' => 'با تخفیف',
            'phone' => '09120000003',
            'status' => 'آمد',
            'done' => 'انجام شد',
            'amount' => 700000,
            'discount' => 100000,
            'description' => 'تخفیف مناسبتی',
            'services' => [['name' => 'لیزر', 'price' => 800000, 'quantity' => 1]],
        ]);

        $result = $this->reports()->drilldown('discount_total', $this->filters());

        $this->assertSame('discount_total', $result['metric']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('1404-08-12', $result['rows'][0]['date']);
        $this->assertSame(100000.0, $result['rows'][0]['amount']);
        $this->assertSame('تخفیف مناسبتی', $result['rows'][0]['reason']);
        $this->assertSame('لیزر', $result['rows'][0]['service']);
        $this->assertSame(100000.0, $result['total']);
    }

    public function test_empty_drilldown_returns_empty_rows(): void
    {
        $result = $this->reports()->drilldown('receivables', $this->filters());

        $this->assertCount(0, $result['rows']);
        $this->assertSame(0.0, $result['total']);
    }
}
