<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_automatic_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('automatic_sms_projects')->nullOnDelete();
            $table->foreignId('scenario_id')->nullable()->constrained('automatic_sms_scenarios')->nullOnDelete();
            $table->foreignId('scenario_step_id')->nullable()->constrained('automatic_sms_scenario_steps')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->string('inventory_tag');
            $table->string('sms_template')->nullable();
            $table->unsignedSmallInteger('days_after');
            $table->time('send_at');
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->string('status', 40)->default('waiting_completion')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('disabled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'scenario_step_id'], 'appointment_auto_sms_step_unique');
            $table->index(['appointment_id', 'is_enabled', 'status'], 'appointment_auto_sms_summary_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_automatic_sms');
    }
};
