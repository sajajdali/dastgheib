<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_note_messages', function (Blueprint $table) {
            $table->boolean('requires_secretary_attention')->default(false)->after('audio_duration')->index();
            $table->timestamp('secretary_seen_at')->nullable()->after('requires_secretary_attention');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_note_messages', function (Blueprint $table) {
            $table->dropColumn(['requires_secretary_attention', 'secretary_seen_at']);
        });
    }
};
