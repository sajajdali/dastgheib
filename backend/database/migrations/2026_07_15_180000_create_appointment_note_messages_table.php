<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_note_messages', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_key', 190)->index();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('message_type', ['text', 'audio'])->default('text');
            $table->text('message')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('audio_duration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_note_messages');
    }
};
