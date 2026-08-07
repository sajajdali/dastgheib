<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_service_tickets', function (Blueprint $table) {
            $table->string('answer_attachment_name')->nullable()->after('answered_at');
            $table->string('answer_attachment_path')->nullable()->after('answer_attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('central_service_tickets', function (Blueprint $table) {
            $table->dropColumn(['answer_attachment_name', 'answer_attachment_path']);
        });
    }
};
