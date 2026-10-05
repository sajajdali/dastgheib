<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('reversed_at')->index();
        });

        Schema::create('referral_reward_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('referrer_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('referred_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('service_key', 190)->nullable();
            $table->string('service_name');
            $table->decimal('received_amount', 15, 2);
            $table->string('reward_type', 20);
            $table->decimal('reward_value', 15, 2);
            $table->decimal('reward_amount', 15, 2);
            $table->json('calculation_snapshot')->nullable();
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['wallet_transaction_id', 'service_key'], 'referral_reward_transaction_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_reward_lines');
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn('expires_at'));
    }
};
