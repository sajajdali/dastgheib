<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->unique()->constrained('inventories')->cascadeOnDelete();
            $table->boolean('booking_enabled')->default(false);
            $table->boolean('use_default_schedule')->default(true);
            $table->boolean('requires_doctor')->default(false);
            $table->boolean('requires_operator')->default(false);
            $table->string('assignment_mode', 20)->default('auto');
            $table->unsignedSmallInteger('slot_interval_minutes')->nullable();
            $table->boolean('conflict_check_enabled')->default(true);
            $table->boolean('online_enabled')->default(false);
            $table->boolean('online_payment_enabled')->default(false);
            $table->boolean('online_cancellation_enabled')->default(false);
            $table->time('online_start_time')->nullable();
            $table->time('online_end_time')->nullable();
            $table->unsignedSmallInteger('booking_start_after_days')->default(0);
            $table->unsignedSmallInteger('booking_available_days')->default(30);
            $table->json('extra_settings')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_booking_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->string('role', 20);
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['inventory_id', 'doctor_id']);
            $table->unique(['inventory_id', 'staff_id']);
        });

        Schema::create('inventory_booking_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_resource_id')->constrained('inventory_booking_resources')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_interval_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['booking_resource_id', 'weekday'], 'ib_availability_weekday_idx');
        });

        Schema::create('inventory_booking_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_id')->constrained('inventory_booking_availabilities')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_booking_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('booking_resource_id')->nullable()->constrained('inventory_booking_resources')->cascadeOnDelete();
            $table->date('exception_date');
            $table->string('type', 20)->default('closed');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['inventory_id', 'exception_date']);
        });

        Schema::create('inventory_booking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->string('rule_type', 80);
            $table->json('rule_value')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['inventory_id', 'rule_type']);
        });

        Schema::create('inventory_booking_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('appointment_service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventories')->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('source', 20)->default('admin');
            $table->string('online_payment_status', 30)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'starts_at', 'ends_at']);
            $table->index(['staff_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_service_bookings');
        Schema::dropIfExists('inventory_booking_audits');
        Schema::dropIfExists('inventory_booking_rules');
        Schema::dropIfExists('inventory_booking_exceptions');
        Schema::dropIfExists('inventory_booking_breaks');
        Schema::dropIfExists('inventory_booking_availabilities');
        Schema::dropIfExists('inventory_booking_resources');
        Schema::dropIfExists('inventory_booking_settings');
    }
};
