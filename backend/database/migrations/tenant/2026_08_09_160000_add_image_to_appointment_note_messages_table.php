<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_note_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('appointment_note_messages', 'image_path')) {
                $table->string('image_path')->nullable()->after('audio_duration');
            }
        });

        DB::statement("ALTER TABLE appointment_note_messages MODIFY message_type ENUM('text', 'audio', 'image') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE appointment_note_messages MODIFY message_type ENUM('text', 'audio') NOT NULL DEFAULT 'text'");

        Schema::table('appointment_note_messages', function (Blueprint $table) {
            if (Schema::hasColumn('appointment_note_messages', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
