<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
        {
            // ۱. اضافه کردن فیلد موجودی به جدول بیماران
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'wallet_balance')) {
                    $table->decimal('wallet_balance', 15, 2)->default(0)->after('financial_status');
                }
            });

            // ۲. ساخت جدول تراکنش‌های کیف پول
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
                $table->enum('type', ['deposit', 'withdraw']); // واریز یا برداشت
                $table->decimal('amount', 15, 2);
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('wallet_transactions');
            
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }
};
