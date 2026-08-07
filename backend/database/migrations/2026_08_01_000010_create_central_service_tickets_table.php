<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->string('tenant_name')->nullable();
            $table->string('user_name')->nullable();
            $table->string('subject');
            $table->text('question');
            $table->string('status', 24)->default('open')->index();
            $table->text('answer')->nullable();
            $table->string('answered_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_service_tickets');
    }
};
