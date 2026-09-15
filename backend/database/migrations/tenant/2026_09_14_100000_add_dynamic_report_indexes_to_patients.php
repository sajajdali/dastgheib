<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = collect(Schema::getIndexes('patients'))->pluck('name')->all();
        $indexes = [
            'patients_birth_date_report_idx' => 'birth_date',
            'patients_first_name_report_idx' => 'first_name',
            'patients_last_name_report_idx' => 'last_name',
            'patients_phone_report_idx' => 'phone',
            'patients_gender_report_idx' => 'gender',
            'patients_city_report_idx' => 'city',
        ];

        Schema::table('patients', function (Blueprint $table) use ($existing, $indexes) {
            foreach ($indexes as $name => $column) {
                if (! in_array($name, $existing, true)) $table->index($column, $name);
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('patients'))->pluck('name')->all();
        Schema::table('patients', function (Blueprint $table) use ($existing) {
            foreach ([
                'patients_birth_date_report_idx', 'patients_first_name_report_idx', 'patients_last_name_report_idx',
                'patients_phone_report_idx', 'patients_gender_report_idx', 'patients_city_report_idx',
            ] as $name) {
                if (in_array($name, $existing, true)) $table->dropIndex($name);
            }
        });
    }
};
