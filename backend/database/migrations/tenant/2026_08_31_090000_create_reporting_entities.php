<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('starts_on', 10)->nullable()->index();
            $table->string('ends_on', 10)->nullable()->index();
            $table->decimal('budget', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('occurred_on', 10)->index();
            $table->string('category', 40)->index();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->string('type', 20)->default('expense');
            $table->string('payment_status', 20)->default('paid');
            $table->string('party')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('payment_method')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['occurred_on', 'category']);
        });

        Schema::create('resource_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->index();
            $table->string('resource_type', 20)->index();
            $table->unsignedBigInteger('resource_id')->index();
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['month', 'resource_type', 'resource_id']);
        });

        Schema::create('satisfaction_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('answered_on', 10)->index();
            $table->timestamps();
        });
        Schema::create('satisfaction_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('satisfaction_response_id')->constrained()->cascadeOnDelete();
            $table->string('question_key');
            $table->string('question_label');
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->index(['question_key', 'score']);
        });

        Schema::create('photo_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_media_id')->constrained()->cascadeOnDelete();
            $table->string('service_tag')->index();
            $table->boolean('is_qualified')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_on', 10)->index();
            $table->timestamps();
            $table->unique(['patient_media_id', 'service_tag']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->index(['month', 'status'], 'appointments_report_month_status_idx');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->index(['date', 'campaign_id'], 'contacts_report_date_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) { $table->dropIndex('contacts_report_date_campaign_idx'); $table->dropConstrainedForeignId('campaign_id'); });
        Schema::table('appointments', function (Blueprint $table) { $table->dropIndex('appointments_report_month_status_idx'); $table->dropConstrainedForeignId('campaign_id'); });
        Schema::dropIfExists('photo_quality_reviews');
        Schema::dropIfExists('satisfaction_answers');
        Schema::dropIfExists('satisfaction_responses');
        Schema::dropIfExists('resource_adjustments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('campaigns');
    }
};
