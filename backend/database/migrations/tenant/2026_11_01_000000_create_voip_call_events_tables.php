<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voip_call_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('call_id', 100);
            $table->unsignedInteger('seq');
            $table->string('event', 40);
            $table->dateTime('occurred_at', 3);
            $table->string('phone', 30)->nullable();
            $table->longText('payload');
            $table->timestamps();
            $table->index(['call_id', 'seq']);
        });

        Schema::create('voip_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('call_id', 100)->unique();
            $table->string('phone', 30)->nullable()->index();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('patient_name')->nullable();
            $table->string('state', 60)->default('شروع تماس');
            $table->string('status', 40)->nullable();
            $table->string('queue')->nullable();
            $table->string('operator')->nullable();
            $table->unsignedInteger('latest_seq')->default(0);
            $table->dateTime('started_at', 3)->nullable();
            $table->dateTime('ended_at', 3)->nullable();
            $table->dateTime('last_event_at', 3)->nullable();
            $table->timestamps();
            $table->index(['last_event_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_calls');
        Schema::dropIfExists('voip_call_events');
    }
};
