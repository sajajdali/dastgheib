<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_financial_transactions', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('occurred_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->index(['appointment_id', 'type', 'voided_at'], 'aft_appointment_type_voided_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_financial_transactions', function (Blueprint $table) {
            $table->dropIndex('aft_appointment_type_voided_idx');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};
