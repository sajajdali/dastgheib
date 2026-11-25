<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignLeadSync;
use Illuminate\Console\Command;

/**
 * برای اثبات عملکرد پیگیری با دادهٔ حجیم روی tenant محلی.
 *
 * مثال:
 *   php artisan followups:seed-heavy --tenant=demo --rows=3000
 */
class SeedHeavyFollowupsCommand extends Command
{
    protected $signature = 'followups:seed-heavy
        {--tenant=demo : شناسهٔ tenant}
        {--rows=2500 : تعداد سرنخ برای کمپین آزمایشی}
        {--campaign= : شناسهٔ کمپین موجود؛ خالی = ساخت کمپین جدید}';

    protected $description = 'Seed a large follow-up campaign to verify lean list + pagination performance';

    public function handle(CampaignLeadSync $sync): int
    {
        $tenantId = (string) $this->option('tenant');
        $rows = max(1, (int) $this->option('rows'));
        tenancy()->initialize($tenantId);

        $campaignId = $this->option('campaign');
        $campaign = $campaignId
            ? Campaign::query()->findOrFail($campaignId)
            : Campaign::query()->create([
                'name' => 'تست عملکرد پیگیری',
                'budget' => 0,
                'active' => true,
                'ui_payload' => [
                    'title' => 'تست عملکرد پیگیری',
                    'source' => 'تست',
                    'sourceName' => 'seed-heavy',
                    'date' => now()->toDateString(),
                    'cost' => 0,
                    'description' => 'دادهٔ مصنوعی برای سنجش بارگذاری پیگیری',
                    'banners' => [],
                    'campaignStatus' => 'active',
                    'pinned' => false,
                    'rows' => [],
                ],
            ]);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $payloadRows = [];
        $this->info("Generating {$rows} leads…");

        for ($i = 1; $i <= $rows; $i++) {
            $followUp = '';
            if ($i % 40 === 0) {
                $followUp = $today;
            } elseif ($i % 35 === 0) {
                $followUp = $yesterday;
            }

            $payloadRows[] = [
                '_localId' => "perf-{$campaign->id}-{$i}",
                'fullName' => "مراجع تست {$i}",
                'phone' => sprintf('0900%07d', $i % 10000000),
                'gender' => $i % 2 === 0 ? 'زن' : 'مرد',
                'consultant' => ['سارا', 'مبینا', 'زهرا'][$i % 3],
                'source' => ['اینستاگرام', 'گوگل', 'معرفی'][$i % 3],
                'status' => $i % 11 === 0 ? 'پاسخ داد' : ($i % 17 === 0 ? 'پاسخ نداد' : ''),
                'interest' => $i % 23 === 0 ? 'ok' : ($i % 13 === 0 ? '2' : ''),
                'contactDate' => now()->subDays($i % 60)->toDateString(),
                'contactTime' => sprintf('%02d:%02d', 9 + ($i % 8), ($i * 3) % 60),
                'followUpDate' => $followUp,
                'description' => $i % 9 === 0 ? "یادداشت تست شماره {$i}" : '',
                'reason' => $i % 19 === 0 ? 'گران' : '',
            ];
        }

        $started = microtime(true);
        $sync->replaceAll($campaign, $payloadRows);
        // برای دادهٔ خیلی حجیم، JSONِ ui_payload را پر نمی‌کنیم (همان مشکل مشتری)؛
        // منبع حقیقت جدول campaign_leads است و API سبک از همان می‌خواند.
        if ($rows <= 800) {
            $sync->mirrorLeadsIntoPayload($campaign->fresh());
        } else {
            $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
            $payload['rows'] = [];
            $payload['rows_mirrored'] = false;
            $campaign->ui_payload = $payload;
            $campaign->save();
        }
        $elapsed = round((microtime(true) - $started) * 1000);

        $stats = $sync->statsForCampaign($campaign->getKey());
        $this->table(
            ['campaign_id', 'rows', 'sync_ms', 'today', 'missed', 'total'],
            [[$campaign->id, $rows, $elapsed, $stats['today'], $stats['missed'], $stats['total']]]
        );

        return self::SUCCESS;
    }
}
