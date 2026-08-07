<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'patient_history')) {
                $table->text('patient_history')->nullable()->after('financial_status');
            }

            if (! Schema::hasColumn('patients', 'medical_history')) {
                $table->text('medical_history')->nullable()->after('patient_history');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['patient_history', 'medical_history'],
                fn (string $column) => Schema::hasColumn('patients', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
