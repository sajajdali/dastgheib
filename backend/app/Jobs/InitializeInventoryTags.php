<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class InitializeInventoryTags
{
    // Keep aligned with the default zone filter in products.vue (covered by a test).
    public const ZONES = [
        'لب', 'بینی', 'زاویه فک', 'چانه', 'گونه', 'پیشانی', 'خط اخم', 'غبغب',
        'مو', 'شقیقه', 'خط لبخند', 'اطراف چشم', 'اطراف لب', 'ماریونت', 'ابرو',
        'کل صورت', 'بدن', 'گوش',
    ];

    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            // Only provision a new site's defaults. Retrying provisioning must
            // preserve even an intentionally empty or customized tag list.
            DB::table('app_settings')->insertOrIgnore([
                'key' => 'service_tags',
                'value' => json_encode(array_map(
                    fn (string $name) => ['name' => $name, 'sms_template' => ''],
                    self::ZONES
                ), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
