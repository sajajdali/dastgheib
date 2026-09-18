<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'marital_status')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('marital_status', 20)->nullable()->after('father_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'marital_status')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('marital_status');
            });
        }
    }
};
