<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30); // payment, deposit, debt, debt_settlement
            $table->decimal('amount', 15, 0);
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_account', 100)->nullable();
            $table->string('reference_number', 190)->nullable();
            $table->date('due_date')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['patient_id', 'type', 'occurred_at'], 'aft_patient_type_time_idx');
            $table->index(['appointment_id', 'type'], 'aft_appointment_type_idx');
        });

        Schema::create('appointment_financial_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('appointment_financial_transactions')->cascadeOnDelete();
            $table->string('service_key', 190)->nullable();
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('service')->nullable();
            $table->string('parent_service')->nullable();
            $table->boolean('is_addon')->default(false);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('gross_amount', 15, 0)->default(0);
            $table->decimal('discount_amount', 15, 0)->default(0);
            $table->decimal('allocated_amount', 15, 0);
            $table->timestamps();
            $table->index(['service', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_financial_allocations');
        Schema::dropIfExists('appointment_financial_transactions');
    }
};
