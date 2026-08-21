<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->select(['id', 'data'])
            ->orderBy('id')
            ->each(function (object $tenant): void {
                $data = json_decode((string) $tenant->data, true);
                $data = is_array($data) ? $data : [];
                $moduleIds = is_array($data['module_ids'] ?? null)
                    ? $data['module_ids']
                    : [];

                $data['module_ids'] = Tenant::withBaseModules($moduleIds);

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            });
    }

    public function down(): void
    {
        // ماژول‌های پایه بخشی از قرارداد دائمی هر tenant هستند و حذف نمی‌شوند.
    }
};
