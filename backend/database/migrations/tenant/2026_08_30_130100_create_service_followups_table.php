<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedBigInteger('inventory_id')->nullable()->index();
            $table->string('service_name');
            $table->string('patient_name')->nullable();
            $table->string('patient_phone', 30)->nullable()->index();
            $table->dateTime('completed_at');
            $table->date('due_date')->index();
            $table->unsignedInteger('followup_days');
            $table->string('status', 30)->default('pending')->index();
            $table->text('action_note')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_key')->unique();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('service_followups'); }
};
