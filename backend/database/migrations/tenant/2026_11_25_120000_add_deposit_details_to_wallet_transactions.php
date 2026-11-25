<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'payment_method')) {
                $table->string('payment_method', 100)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('wallet_transactions', 'payment_account')) {
                $table->string('payment_account', 100)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('wallet_transactions', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('metadata');
            }
        });

        if (Schema::hasColumn('wallet_transactions', 'occurred_at')) {
            DB::table('wallet_transactions')
                ->whereNull('occurred_at')
                ->update(['occurred_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            foreach (['occurred_at', 'payment_account', 'payment_method'] as $column) {
                if (Schema::hasColumn('wallet_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
