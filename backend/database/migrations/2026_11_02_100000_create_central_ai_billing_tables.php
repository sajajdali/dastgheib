<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// همهٔ مبالغ به میکرودلار (۱ دلار = ۱٬۰۰۰٬۰۰۰) و عدد صحیح ذخیره می‌شوند.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('markup_multiplier', 8, 4)->default(1.3);
            $table->unsignedBigInteger('min_balance_micros')->default(1000000);
            $table->unsignedBigInteger('low_balance_micros')->default(3000000);
            $table->timestamps();
        });
        DB::table('central_ai_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        Schema::create('central_ai_model_prices', function (Blueprint $table) {
            $table->id();
            $table->string('model', 80);
            $table->string('kind', 20)->default('chat');
            $table->unsignedBigInteger('input_per_m')->default(0);
            $table->unsignedBigInteger('cached_input_per_m')->nullable();
            $table->unsignedBigInteger('output_per_m')->default(0);
            $table->unsignedBigInteger('audio_input_per_m')->nullable();
            $table->unsignedBigInteger('per_minute')->nullable();
            $table->dateTime('effective_from');
            $table->unsignedBigInteger('central_admin_id')->nullable();
            $table->timestamps();
            $table->index(['model', 'effective_from']);
        });

        Schema::create('central_ai_wallets', function (Blueprint $table) {
            $table->string('tenant_id')->primary();
            $table->unsignedBigInteger('balance_micros')->default(0);
            $table->decimal('markup_multiplier', 8, 4)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('central_ai_holds', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('amount_micros');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'expires_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('central_ai_usage', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->uuid('request_group');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedTinyInteger('round')->default(0);
            $table->string('type', 20);
            $table->string('model', 80);
            $table->string('response_id', 120)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('audio_input_tokens')->default(0);
            $table->decimal('audio_seconds', 10, 2)->nullable();
            $table->foreignId('price_id')->nullable()->constrained('central_ai_model_prices')->nullOnDelete();
            $table->decimal('markup_multiplier', 8, 4);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->unsignedBigInteger('charge_micros')->default(0);
            $table->unsignedBigInteger('uncollected_micros')->default(0);
            $table->string('status', 20)->default('completed');
            $table->boolean('estimated')->default(false);
            $table->char('jalali_date', 10);
            $table->char('jalali_week', 10);
            $table->char('jalali_month', 7);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'jalali_date']);
            $table->index(['tenant_id', 'user_id', 'jalali_date']);
            $table->index(['tenant_id', 'model']);
            $table->index('request_group');
        });

        Schema::create('central_ai_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('type', 20);
            $table->bigInteger('amount_micros');
            $table->unsignedBigInteger('balance_after_micros');
            $table->foreignId('usage_id')->nullable()->constrained('central_ai_usage')->nullOnDelete();
            $table->unsignedBigInteger('central_admin_id')->nullable();
            $table->unsignedBigInteger('paid_toman')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_ai_wallet_transactions');
        Schema::dropIfExists('central_ai_usage');
        Schema::dropIfExists('central_ai_holds');
        Schema::dropIfExists('central_ai_wallets');
        Schema::dropIfExists('central_ai_model_prices');
        Schema::dropIfExists('central_ai_settings');
    }
};
