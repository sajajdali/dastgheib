<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasColumn('appointments', 'description')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasColumn('appointments', 'description')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('description', 255)->nullable()->change();
        });
    }
};
