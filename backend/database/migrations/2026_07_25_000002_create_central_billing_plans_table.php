<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_days');
            $table->unsignedBigInteger('base_price')->default(0);
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('central_user_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('included_users')->default(1);
            $table->unsignedBigInteger('extra_user_price')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_user_pricing');
        Schema::dropIfExists('central_billing_plans');
    }
};
