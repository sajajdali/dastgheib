<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_builder_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('source', 32)->default('appointments')->index();
            $table->json('columns');
            $table->foreignId('created_by')->nullable()->index();
            $table->string('creator_name')->nullable();
            // شناسهٔ قالب در localStorage مرورگر — برای جلوگیری از درج تکراری هنگام مهاجرت.
            $table->string('client_key', 64)->nullable()->unique();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_builder_templates');
    }
};
