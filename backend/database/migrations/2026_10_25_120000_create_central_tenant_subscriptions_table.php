<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('plan_id')->nullable()->constrained('central_billing_plans')->nullOnDelete();
            $table->string('plan_name');
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('licensed_users')->default(1);
            $table->unsignedInteger('included_users')->default(1);
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedBigInteger('extra_user_price')->default(0);
            $table->unsignedBigInteger('price_paid')->default(0);
            $table->dateTime('starts_at')->index();
            $table->dateTime('expires_at')->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->string('payment_reference')->nullable()->unique();
            $table->foreignId('renewed_from_id')->nullable()->constrained('central_tenant_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'expires_at'], 'tenant_subscription_status_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_tenant_subscriptions');
    }
};
