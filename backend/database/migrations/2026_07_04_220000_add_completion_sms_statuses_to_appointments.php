<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('appointments', fn (Blueprint $table) => $table->json('completion_sms_statuses')->nullable()->after('info_sms')); }
    public function down(): void { Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('completion_sms_statuses')); }
};
