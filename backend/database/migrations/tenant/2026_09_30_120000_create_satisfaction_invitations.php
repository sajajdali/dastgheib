<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satisfaction_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 12)->unique();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->json('form_snapshot');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('satisfaction_answers', function (Blueprint $table) {
            $table->string('question_type', 20)->default('rating')->after('question_label');
            $table->string('option_key')->nullable()->after('question_type');
            $table->text('answer_value')->nullable()->after('option_key');
            $table->unsignedTinyInteger('score')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_invitations');
        Schema::table('satisfaction_answers', function (Blueprint $table) {
            $table->dropColumn(['question_type', 'option_key', 'answer_value']);
        });
    }
};
