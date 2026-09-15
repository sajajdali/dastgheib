<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'appointments_referrer_report_idx' => 'referrer_phone',
        'appointments_file_number_report_idx' => 'file_number',
        'appointments_phone_report_idx' => 'phone',
    ];

    public function up(): void
    {
        $existing = collect(Schema::getIndexes('appointments'))->pluck('name')->all();
        Schema::table('appointments', function (Blueprint $table) use ($existing) {
            foreach (self::INDEXES as $name => $column) {
                if (! in_array($name, $existing, true)) $table->index($column, $name);
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('appointments'))->pluck('name')->all();
        Schema::table('appointments', function (Blueprint $table) use ($existing) {
            foreach (array_keys(self::INDEXES) as $name) {
                if (in_array($name, $existing, true)) $table->dropIndex($name);
            }
        });
    }
};
