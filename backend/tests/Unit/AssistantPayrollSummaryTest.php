<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\AssistantToolRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class AssistantPayrollSummaryTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('resource_earning_lines');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('doctors');
        Mockery::close();
        parent::tearDown();
    }

    public function test_payroll_summary_sums_active_commission_for_named_staff_month(): void
    {
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->decimal('salary', 15, 2)->default(0);
        });
        Schema::create('staff', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->decimal('salary', 15, 2)->default(0);
        });
        Schema::create('resource_earning_lines', function (Blueprint $table): void {
            $table->id(); $table->string('month', 7); $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable(); $table->string('resource_name');
            $table->string('earning_type'); $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('active'); $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $staffId = DB::table('staff')->insertGetId(['name' => 'الهام رضایی', 'salary' => 5000000]);
        DB::table('doctors')->insert(['name' => 'الهام کاظمی', 'salary' => 0]);
        DB::table('resource_earning_lines')->insert([
            ['month' => '1405-08', 'resource_type' => 'staff', 'resource_id' => $staffId, 'resource_name' => 'الهام رضایی',
                'earning_type' => 'base_commission', 'amount' => 120000, 'status' => 'active', 'deleted_at' => null],
            ['month' => '1405-08', 'resource_type' => 'staff', 'resource_id' => $staffId, 'resource_name' => 'الهام رضایی',
                'earning_type' => 'inventory_commission', 'amount' => 80000, 'status' => 'active', 'deleted_at' => null],
            ['month' => '1405-08', 'resource_type' => 'staff', 'resource_id' => $staffId, 'resource_name' => 'الهام رضایی',
                'earning_type' => 'inventory_commission', 'amount' => 999999, 'status' => 'deleted', 'deleted_at' => null],
            ['month' => '1405-07', 'resource_type' => 'staff', 'resource_id' => $staffId, 'resource_name' => 'الهام رضایی',
                'earning_type' => 'base_commission', 'amount' => 50000, 'status' => 'active', 'deleted_at' => null],
        ]);

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'payroll.view');

        $ambiguous = app(AssistantToolExecutor::class)->execute($user, 'get_payroll_summary', [
            'name' => 'الهام', 'month' => '1405-08', 'resource_type' => null,
        ]);
        $this->assertSame('چند نفر هم‌نام پیدا شد؛ بپرس کدام‌یک.', $ambiguous['model']['error']);
        $this->assertCount(2, $ambiguous['model']['matches']);

        $result = app(AssistantToolExecutor::class)->execute($user, 'get_payroll_summary', [
            'name' => 'الهام رضایی', 'month' => '1405-08', 'resource_type' => 'staff',
        ]);
        $this->assertSame(200000, $result['model']['summary']['total_commission']);
        $this->assertSame(120000, $result['model']['summary']['service_commission']);
        $this->assertSame(80000, $result['model']['summary']['inventory_commission']);
        $this->assertSame(5200000, $result['model']['summary']['total_earned']);
        $this->assertSame(200000, $result['presentation']['value']);
        $this->assertSame('payroll', $result['presentation']['page']);
    }

    public function test_payroll_tool_requires_payroll_permission(): void
    {
        $denied = Mockery::mock(User::class);
        $denied->shouldReceive('can')->andReturn(false);
        $allowed = Mockery::mock(User::class);
        $allowed->shouldReceive('can')->andReturnUsing(fn (string $permission) => $permission === 'payroll.view');

        $registry = new AssistantToolRegistry;
        $this->assertFalse($registry->allowed($denied, 'get_payroll_summary'));
        $this->assertTrue($registry->allowed($allowed, 'get_payroll_summary'));
    }
}
