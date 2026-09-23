<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_schedule_months', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();
            $table->timestamps();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('ui_payload')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('ui_payload');
        });
        Schema::dropIfExists('appointment_schedule_months');
    }
};
