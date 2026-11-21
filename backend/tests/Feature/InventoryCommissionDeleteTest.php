<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryCommission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryCommissionDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('default_commission_type')->default('percent');
            $table->decimal('default_commission_value', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('inventory_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name');
            $table->string('commission_type');
            $table->decimal('commission_value', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_clearing_default_commission_removes_the_percent_from_only_the_chosen_services(): void
    {
        $global = Inventory::query()->create(['name' => 'بوتاکس', 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        $sameRule = Inventory::query()->create(['name' => 'فیلر', 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        $kept = Inventory::query()->create(['name' => 'نخ', 'default_commission_type' => 'fixed', 'default_commission_value' => 70000]);
        InventoryCommission::query()->create([
            'inventory_id' => $global->id,
            'recipient_type' => 'doctor',
            'recipient_id' => 4,
            'recipient_name' => 'دکتر نمونه',
            'commission_type' => 'percent',
            'commission_value' => 15,
        ]);

        $response = $this->deleteJson('/api/inventory/commissions', [
            'inventory_ids' => [$global->id, $sameRule->id],
        ]);

        $response->assertOk();
        $this->assertSame(0.0, (float) $global->fresh()->default_commission_value);
        $this->assertSame(0.0, (float) $sameRule->fresh()->default_commission_value);
        $this->assertSame(70000.0, (float) $kept->fresh()->default_commission_value);
        $this->assertSame(1, InventoryCommission::query()->count());
        $response->assertJsonPath('items.0.default_commission_value', 0);
    }

    public function test_deleting_one_service_commission_does_not_clear_the_others(): void
    {
        $one = Inventory::query()->create(['name' => 'میکرونیدلینگ', 'default_commission_type' => 'percent', 'default_commission_value' => 12]);
        $other = Inventory::query()->create(['name' => 'هایفوتراپی', 'default_commission_type' => 'percent', 'default_commission_value' => 8]);

        $this->deleteJson('/api/inventory/commissions', ['inventory_ids' => [$one->id]])->assertOk();

        $this->assertSame(0.0, (float) $one->fresh()->default_commission_value);
        $this->assertSame(8.0, (float) $other->fresh()->default_commission_value);
    }

    public function test_deleting_a_person_commission_removes_the_row(): void
    {
        $service = Inventory::query()->create(['name' => 'ژل', 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        $person = InventoryCommission::query()->create([
            'inventory_id' => $service->id,
            'recipient_type' => 'staff',
            'recipient_id' => 9,
            'recipient_name' => 'پرسنل نمونه',
            'commission_type' => 'percent',
            'commission_value' => 7,
        ]);
        $other = InventoryCommission::query()->create([
            'inventory_id' => $service->id,
            'recipient_type' => 'doctor',
            'recipient_id' => 3,
            'recipient_name' => 'پزشک دیگر',
            'commission_type' => 'percent',
            'commission_value' => 4,
        ]);

        $response = $this->deleteJson('/api/inventory/commissions', ['commission_id' => $person->id]);

        $response->assertOk();
        $this->assertNull(InventoryCommission::query()->find($person->id));
        $this->assertNotNull(InventoryCommission::query()->find($other->id));
        $this->assertSame(10.0, (float) $service->fresh()->default_commission_value);
        $response->assertJsonPath('items.0.commissions.0.id', $other->id);
    }

    public function test_missing_commission_target_is_rejected(): void
    {
        $this->deleteJson('/api/inventory/commissions', [])->assertStatus(422);
        $this->deleteJson('/api/inventory/commissions', ['inventory_ids' => [99]])->assertStatus(422);
        $this->deleteJson('/api/inventory/commissions', ['commission_id' => 99])->assertStatus(422);
    }
}
