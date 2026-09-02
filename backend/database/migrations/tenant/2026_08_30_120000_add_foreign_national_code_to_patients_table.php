<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'foreign_national_code')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('foreign_national_code', 30)->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'foreign_national_code')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('foreign_national_code');
            });
        }
    }
};
