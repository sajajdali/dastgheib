<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('doctors', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('doctors', 'service_section_ids')) {
                $table->json('service_section_ids')->nullable()->after('available_days');
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'service_section_ids')) {
                $table->dropColumn('service_section_ids');
            }

            if (Schema::hasColumn('doctors', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
