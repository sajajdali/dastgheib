<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('debt');
            }

            if (! Schema::hasColumn('appointments', 'payment_account')) {
                $table->string('payment_account')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['payment_account', 'payment_method'],
                fn (string $column) => Schema::hasColumn('appointments', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
