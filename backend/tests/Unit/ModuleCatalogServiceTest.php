<?php

namespace Tests\Unit;

use App\Models\CentralModuleCatalog;
use App\Services\ModuleCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModuleCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_and_store_list_uses_central_prices(): void
    {
        $service = new ModuleCatalogService();
        $central = $service->listForCentral();
        $this->assertNotEmpty($central);
        $this->assertDatabaseCount('central_module_catalog', count(config('store_modules.modules')));

        CentralModuleCatalog::query()->where('module_key', 'attendance')->update([
            'price' => 999000,
            'periods' => [['key' => 'one_time', 'label' => 'یک‌باره', 'price' => 999000]],
            'enabled' => true,
        ]);

        $store = collect($service->listForStore())->firstWhere('key', 'attendance');
        $this->assertSame(999000, $store['price']);
        $this->assertFalse($store['comingSoon']);
    }

    public function test_checkout_rejects_stale_client_price(): void
    {
        $service = new ModuleCatalogService();
        $service->ensureSeeded();
        CentralModuleCatalog::query()->where('module_key', 'attendance')->update([
            'price' => 111000,
            'periods' => [['key' => 'one_time', 'label' => 'یک‌باره', 'price' => 111000]],
            'enabled' => true,
        ]);

        $this->expectException(ValidationException::class);
        $service->validateCheckoutItems([
            ['key' => 'attendance', 'title' => 'حضور غیاب', 'price' => 2800000, 'billing_period' => 'one_time'],
        ]);
    }

    public function test_sync_replaces_catalog(): void
    {
        $service = new ModuleCatalogService();
        $service->ensureSeeded();

        $synced = $service->syncFromCentral([
            [
                'id' => 'custom_mod',
                'name' => 'ماژول تست',
                'price' => 5000,
                'enabled' => true,
                'periods' => [['key' => 'one_time', 'label' => 'یک‌باره', 'price' => 5000]],
            ],
        ]);

        $this->assertCount(1, $synced);
        $this->assertSame('custom_mod', $synced[0]['id']);
        $this->assertDatabaseCount('central_module_catalog', 1);
    }
}
