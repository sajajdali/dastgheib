<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_report_rows', function (Blueprint $table) {
            $table->dropUnique(['report_id', 'patient_id']);
            $table->unsignedBigInteger('patient_id')->nullable()->change();
            $table->string('source_type', 40)->nullable()->after('patient_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
        });
        DB::table('dynamic_report_rows')->whereNull('source_type')->update([
            'source_type' => 'patients', 'source_id' => DB::raw('patient_id'),
        ]);
        Schema::table('dynamic_report_rows', function (Blueprint $table) {
            $table->unique(['report_id', 'source_type', 'source_id'], 'dynamic_report_source_unique');
        });
    }

    public function down(): void
    {
        // Report rows are derived snapshots. Remove only non-patient snapshots
        // before restoring the original non-null patient column.
        DB::table('dynamic_report_rows')->where('source_type', '<>', 'patients')->delete();
        Schema::table('dynamic_report_rows', function (Blueprint $table) {
            $table->dropUnique('dynamic_report_source_unique');
            $table->dropColumn(['source_type', 'source_id']);
            $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            $table->unique(['report_id', 'patient_id']);
        });
    }
};
