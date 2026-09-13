<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointment_financial_transactions', 'settled_at')) {
            Schema::table('appointment_financial_transactions', fn (Blueprint $table) =>
                $table->timestamp('settled_at')->nullable()->after('voided_by')
            );
        }
        if (! Schema::hasColumn('appointment_financial_transactions', 'settled_by')) {
            Schema::table('appointment_financial_transactions', fn (Blueprint $table) =>
                $table->foreignId('settled_by')->nullable()->after('settled_at')->constrained('users')->nullOnDelete()
            );
        }
        if (! Schema::hasColumn('appointment_financial_transactions', 'settlement_transaction_id')) {
            Schema::table('appointment_financial_transactions', fn (Blueprint $table) =>
                $table->unsignedBigInteger('settlement_transaction_id')->nullable()->after('settled_by')
            );
        }

        $indexes = collect(Schema::getIndexes('appointment_financial_transactions'))->pluck('name');
        if (! $indexes->contains('aft_settlement_tx_fk')) {
            Schema::table('appointment_financial_transactions', fn (Blueprint $table) =>
                $table->foreign('settlement_transaction_id', 'aft_settlement_tx_fk')
                    ->references('id')->on('appointment_financial_transactions')->nullOnDelete()
            );
        }
        if (! $indexes->contains('aft_appointment_type_settled_idx')) {
            Schema::table('appointment_financial_transactions', fn (Blueprint $table) =>
                $table->index(['appointment_id', 'type', 'settled_at'], 'aft_appointment_type_settled_idx')
            );
        }
    }

    public function down(): void
    {
        Schema::table('appointment_financial_transactions', function (Blueprint $table) {
            $table->dropIndex('aft_appointment_type_settled_idx');
            $table->dropForeign('aft_settlement_tx_fk');
            $table->dropColumn('settlement_transaction_id');
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn('settled_at');
        });
    }
};
