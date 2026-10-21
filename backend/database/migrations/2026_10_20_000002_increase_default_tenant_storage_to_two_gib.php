<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ONE_GIB = 1073741824;
    private const TWO_GIB = 2147483648;

    public function up(): void
    {
        if (! Schema::hasTable('central_tenant_storage')) {
            return;
        }

        DB::table('central_tenant_storage')
            ->where('base_quota_bytes', self::ONE_GIB)
            ->update([
                'base_quota_bytes' => self::TWO_GIB,
                'updated_at' => now(),
            ]);

        Schema::table('central_tenant_storage', function (Blueprint $table) {
            $table->unsignedBigInteger('base_quota_bytes')->default(self::TWO_GIB)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_tenant_storage')) {
            return;
        }

        Schema::table('central_tenant_storage', function (Blueprint $table) {
            $table->unsignedBigInteger('base_quota_bytes')->default(self::ONE_GIB)->change();
        });

        // Existing quotas are intentionally not reduced during rollback.
    }
};
