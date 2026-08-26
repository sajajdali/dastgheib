<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_sms_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // نام تگ انبار؛ عمداً کلید خارجی نیست تا با تغییر تگ، تاریخچه سناریو باقی بماند.
            $table->string('inventory_tag');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('automatic_sms_scenario_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('automatic_sms_scenarios')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedSmallInteger('days_after')->default(1);
            $table->time('send_at');
            $table->string('sms_template');
            $table->timestamps();
            $table->unique(['scenario_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_sms_scenario_steps');
        Schema::dropIfExists('automatic_sms_scenarios');
    }
};
