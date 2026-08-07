<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_store_terms', function (Blueprint $table) {
            $table->id();
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('central_store_term_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_store_term_id')->constrained('central_store_terms')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('tenant_name')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->json('items');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('payable_total')->default(0);
            $table->timestamp('accepted_at')->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('status')->default('paid')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_store_term_acceptances');
        Schema::dropIfExists('central_store_terms');
    }
};
