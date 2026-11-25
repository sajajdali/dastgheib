<?php

use App\Models\Campaign;
use App\Services\CampaignLeadSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سرنخ‌های پیگیری را از JSONِ ui_payload جدا می‌کند تا فهرست کمپین سبک بماند
 * و صفحه‌بندی/فیلتر/ایندکس روی جدول واقعی ممکن شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('local_id', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('full_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('consultant')->nullable();
            $table->string('source')->nullable();
            $table->string('status', 40)->nullable();
            $table->string('interest', 20)->nullable();
            $table->string('reason')->nullable();
            $table->string('contact_date', 16)->nullable();
            $table->string('contact_time', 16)->nullable();
            $table->string('follow_up_date', 16)->nullable();
            $table->unsignedBigInteger('subsection_id')->nullable();
            $table->string('subsection_name')->nullable();
            $table->boolean('appointment_registered')->default(false);
            $table->boolean('appointment_done')->default(false);
            $table->boolean('meaningful')->default(false)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'local_id']);
            $table->index(['campaign_id', 'sort_order']);
            $table->index(['campaign_id', 'follow_up_date']);
            $table->index(['campaign_id', 'contact_date']);
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'consultant']);
            $table->index(['campaign_id', 'interest']);
            $table->index(['phone']);
        });

        if (Schema::hasTable('campaigns')) {
            $sync = app(CampaignLeadSync::class);
            Campaign::query()->orderBy('id')->chunkById(25, function ($campaigns) use ($sync): void {
                foreach ($campaigns as $campaign) {
                    $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                    $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
                    $sync->replaceAll($campaign, $rows);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_leads');
    }
};
