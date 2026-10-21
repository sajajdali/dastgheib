<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_tenant_storage', function (Blueprint $table) {
            $table->string('tenant_id')->primary();
            $table->unsignedBigInteger('base_quota_bytes')->default(2147483648);
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('central_storage_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('size_gb');
            $table->unsignedBigInteger('monthly_price');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('central_storage_orders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('package_id')->nullable()->constrained('central_storage_packages')->nullOnDelete();
            $table->string('package_name');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('monthly_price');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        $now = now();
        DB::table('tenants')->select('id')->orderBy('id')->chunk(500, function ($tenants) use ($now) {
            DB::table('central_tenant_storage')->insert($tenants->map(fn ($tenant) => [
                'tenant_id' => $tenant->id,
                'base_quota_bytes' => 2147483648,
                'used_bytes' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_storage_orders');
        Schema::dropIfExists('central_storage_packages');
        Schema::dropIfExists('central_tenant_storage');
    }
};
