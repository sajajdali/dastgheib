<?php

use App\Models\Campaign;
use App\Support\CampaignFollowupNotes;
use Illuminate\Database\Migrations\Migration;

/**
 * توضیحات تک‌متنی ردیف‌های پیگیری را به لیست یادداشت‌های تاریخ‌دار تبدیل می‌کند
 * و فیلد description را با متن آخرین یادداشت هم‌تراز نگه می‌دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Campaign::query()->orderBy('id')->chunkById(50, function ($campaigns): void {
            foreach ($campaigns as $campaign) {
                $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                $rows = $payload['rows'] ?? null;
                if (! is_array($rows) || $rows === []) continue;

                $normalized = CampaignFollowupNotes::normalizePayloadRows($rows);
                if ($normalized === $rows) continue;

                $payload['rows'] = $normalized;
                $campaign->ui_payload = $payload;
                $campaign->save();
            }
        });
    }

    public function down(): void
    {
        // برگشت کامل ممکن نیست؛ description از قبل نگه داشته شده است.
    }
};
