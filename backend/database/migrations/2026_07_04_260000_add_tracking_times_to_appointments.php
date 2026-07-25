<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'arrived_at')) {
                $table->dateTime('arrived_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('appointments', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('done');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['completed_at', 'arrived_at'],
                fn (string $column) => Schema::hasColumn('appointments', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
