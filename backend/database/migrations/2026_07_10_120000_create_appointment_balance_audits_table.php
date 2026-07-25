<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_balance_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedBigInteger('previous_appointment_id')->nullable();
            $table->string('month', 20)->nullable();
            $table->unsignedInteger('day_num')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('file_number', 50)->nullable();
            $table->decimal('old_debt', 15, 0)->default(0);
            $table->decimal('new_debt', 15, 0)->default(0);
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['month', 'day_num']);
            $table->index(['file_number', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_balance_audits');
    }
};
