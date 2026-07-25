<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'national_id')) {
                $table->string('national_id', 20)->nullable()->after('medical_history');
            }

            if (! Schema::hasColumn('patients', 'father_name')) {
                $table->string('father_name')->nullable()->after('national_id');
            }

            if (! Schema::hasColumn('patients', 'marriage_date')) {
                $table->string('marriage_date', 20)->nullable()->after('father_name');
            }

            if (! Schema::hasColumn('patients', 'education')) {
                $table->string('education')->nullable()->after('marriage_date');
            }

            if (! Schema::hasColumn('patients', 'second_phone')) {
                $table->string('second_phone', 30)->nullable()->after('education');
            }

            if (! Schema::hasColumn('patients', 'address')) {
                $table->text('address')->nullable()->after('second_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['national_id', 'father_name', 'marriage_date', 'education', 'second_phone', 'address'],
                fn (string $column) => Schema::hasColumn('patients', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
