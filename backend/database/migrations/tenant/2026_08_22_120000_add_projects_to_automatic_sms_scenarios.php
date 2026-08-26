<?php

use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_sms_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('automatic_sms_scenarios', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained('automatic_sms_projects')->cascadeOnDelete();
        });

        // داده‌های نسخه‌ی اولیه از بین نروند؛ همگی در یک پروژه‌ی منتقل‌شده قرار می‌گیرند.
        if (AutomaticSmsScenario::query()->exists()) {
            $project = AutomaticSmsProject::create(['name' => 'پروژه منتقل‌شده', 'is_active' => true]);
            AutomaticSmsScenario::query()->whereNull('project_id')->update(['project_id' => $project->id]);
        }
    }

    public function down(): void
    {
        Schema::table('automatic_sms_scenarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('automatic_sms_projects');
    }
};
