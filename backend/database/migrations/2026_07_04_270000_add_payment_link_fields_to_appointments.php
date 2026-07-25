<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'payment_link')) {
                $table->text('payment_link')->nullable()->after('payment_account');
            }

            if (! Schema::hasColumn('appointments', 'payment_link_sent_count')) {
                $table->unsignedInteger('payment_link_sent_count')->default(0)->after('payment_link');
            }

            if (! Schema::hasColumn('appointments', 'payment_link_last_sent_at')) {
                $table->dateTime('payment_link_last_sent_at')->nullable()->after('payment_link_sent_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['payment_link_last_sent_at', 'payment_link_sent_count', 'payment_link'],
                fn (string $column) => Schema::hasColumn('appointments', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
