<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_module_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('module_key')->index();
            $table->string('module_title')->nullable();
            $table->string('billing_period')->default('one_time')->index();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedBigInteger('price_paid')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_paid_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('renewed_from_id')->nullable()->constrained('central_module_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'module_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_module_subscriptions');
    }
};
