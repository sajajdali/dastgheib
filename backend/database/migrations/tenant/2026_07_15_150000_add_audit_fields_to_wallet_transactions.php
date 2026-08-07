<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('description');
            $table->string('source_key', 190)->nullable()->index()->after('source_type');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete()->after('source_key');
            $table->foreignId('reversed_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete()->after('appointment_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('reversed_transaction_id');
            $table->json('metadata')->nullable()->after('created_by');
            $table->timestamp('reversed_at')->nullable()->after('metadata');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('wallet_applied', 15, 2)->default(0)->after('referral_score');
            $table->string('referral_commission_type', 20)->nullable()->after('wallet_applied');
            $table->decimal('referral_commission_value', 15, 2)->default(0)->after('referral_commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['wallet_applied', 'referral_commission_type', 'referral_commission_value']);
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropConstrainedForeignId('reversed_transaction_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['source_key']);
            $table->dropColumn(['source_type', 'source_key', 'metadata', 'reversed_at']);
        });
    }
};
