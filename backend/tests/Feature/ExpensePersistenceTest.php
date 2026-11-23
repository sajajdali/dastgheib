<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpensePersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\EnsureTenantSubscriptionActive::class,
            \App\Http\Middleware\EnforceTenantStorageQuota::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('occurred_on', 10)->index();
            $table->string('category', 40)->index();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->string('type', 20)->default('expense');
            $table->string('payment_status', 20)->default('paid');
            $table->string('party')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('account_type', 40)->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $user = new User();
        $user->id = 11;
        $user->name = 'مدیر هزینه';
        $this->actingAs($user);
    }

    public function test_expense_create_update_and_reload_survive(): void
    {
        $create = $this->postJson('/api/expenses', [
            'occurred_on' => '1404-08-12',
            'category' => 'اجاره',
            'title' => 'اجاره آبان',
            'amount' => 2500000,
            'type' => 'expense',
            'payment_status' => 'paid',
            'party' => 'مالک',
            'invoice_number' => 'A-12',
            'payment_method' => 'کارت به کارت',
            'account_type' => 'اصلی',
            'note' => 'پرداخت ماهانه',
        ])->assertCreated();

        $id = (int) $create->json('id');
        $this->assertDatabaseHas('expenses', [
            'id' => $id,
            'title' => 'اجاره آبان',
            'amount' => 2500000,
            'occurred_on' => '1404-08-12',
            'note' => 'پرداخت ماهانه',
            'account_type' => 'اصلی',
        ]);

        $this->putJson('/api/expenses/'.$id, [
            'occurred_on' => '1404-08-12',
            'category' => 'اجاره',
            'title' => 'اجاره آبان به‌روز',
            'amount' => 2600000,
            'type' => 'expense',
            'payment_status' => 'paid',
            'party' => 'مالک',
            'invoice_number' => 'A-12',
            'payment_method' => 'کارت به کارت',
            'account_type' => 'اصلی',
            'note' => 'اصلاح مبلغ',
        ])->assertOk()
            ->assertJsonPath('title', 'اجاره آبان به‌روز');

        $this->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $id,
                'title' => 'اجاره آبان به‌روز',
                'note' => 'اصلاح مبلغ',
                'occurred_on' => '1404-08-12',
            ]);

        $this->assertSame(1, Expense::query()->count());
    }

    public function test_invalid_expense_keeps_existing_row(): void
    {
        $expense = Expense::query()->create([
            'occurred_on' => '1404-08-01',
            'category' => 'تعمیرات',
            'title' => 'سرویس دستگاه',
            'amount' => 900000,
            'type' => 'expense',
            'payment_status' => 'paid',
            'created_by' => 11,
        ]);

        $this->putJson('/api/expenses/'.$expense->id, [
            'occurred_on' => 'bad-date',
            'category' => 'تعمیرات',
            'title' => 'نباید ذخیره شود',
            'amount' => 1,
            'type' => 'expense',
            'payment_status' => 'paid',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'title' => 'سرویس دستگاه',
            'amount' => 900000,
        ]);
    }
}
