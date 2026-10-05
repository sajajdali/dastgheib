<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('credit_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->foreignId('debit_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('used_at')->index();
            $table->timestamps();
            $table->unique(['credit_transaction_id', 'debit_transaction_id'], 'wallet_credit_debit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transaction_allocations');
    }
};
