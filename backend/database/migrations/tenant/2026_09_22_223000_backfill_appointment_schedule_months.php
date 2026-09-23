<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('appointment_schedule_months') || ! Schema::hasTable('appointments')) return;

        $months = DB::table('appointments')
            ->whereNotNull('month')
            ->where(fn ($query) => $query->whereNull('file_number')->orWhere('file_number', 'not like', 'LOADTEST-%'))
            ->where(function ($query) {
                foreach (['lastname', 'gender', 'phone', 'file_number', 'status', 'doctor', 'consultant', 'source', 'description', 'done'] as $column) {
                    $query->orWhere(fn ($field) => $field->whereNotNull($column)->where($column, '<>', ''));
                }
                $query->orWhere('amount', '>', 0)->orWhere('debt', '>', 0)->orWhere('discount', '>', 0);
            })
            ->distinct()->pluck('month')
            ->filter(fn ($month) => preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])$/', (string) $month));

        $now = now();
        DB::table('appointment_schedule_months')->insertOrIgnore($months->map(fn ($month) => [
            'month' => $month,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        // Historical month rows may also have been explicitly created by users.
        // They must not be removed during rollback.
    }
};
