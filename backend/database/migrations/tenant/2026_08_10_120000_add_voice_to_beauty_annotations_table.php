<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beauty_annotations', function (Blueprint $table) {
            if (! Schema::hasColumn('beauty_annotations', 'voice_path')) {
                $table->string('voice_path')->nullable()->after('note');
            }
            if (! Schema::hasColumn('beauty_annotations', 'voice_duration')) {
                $table->unsignedInteger('voice_duration')->nullable()->after('voice_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beauty_annotations', function (Blueprint $table) {
            if (Schema::hasColumn('beauty_annotations', 'voice_duration')) {
                $table->dropColumn('voice_duration');
            }
            if (Schema::hasColumn('beauty_annotations', 'voice_path')) {
                $table->dropColumn('voice_path');
            }
        });
    }
};
