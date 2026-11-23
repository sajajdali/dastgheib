<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_reminder_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('recipient', 30);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminder_sms_logs');
    }
};
