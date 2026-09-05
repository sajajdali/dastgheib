<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryDefaultAddonsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->nullable();
            $table->string('name')->nullable();
            $table->json('service_tags')->nullable();
            $table->string('amount')->nullable();
            $table->string('price')->nullable();
            $table->integer('count')->default(0);
            $table->integer('stock')->nullable();
            $table->integer('min_stock')->default(5);
            $table->boolean('active')->default(true);
            $table->integer('followup_days')->default(0);
            $table->integer('sort_order')->default(0);
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
        Schema::create('inventory_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->foreignId('addon_inventory_id');
            $table->timestamps();
            $table->unique(['inventory_id', 'addon_inventory_id']);
        });
        Schema::create('inventory_addon_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('amount')->nullable();
            $table->string('price')->nullable();
            $table->integer('stock')->nullable();
            $table->integer('min_stock')->default(5);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('inventory_addon_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->foreignId('inventory_addon_definition_id');
            $table->timestamps();
            $table->unique(['inventory_id', 'inventory_addon_definition_id']);
        });
        Schema::create('doctors', function (Blueprint $table) { $table->id(); $table->string('name'); });
        Schema::create('staff', function (Blueprint $table) { $table->id(); $table->string('name'); });
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('mobile')->nullable(); });
        Schema::create('app_settings', function (Blueprint $table) { $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps(); });
    }

    public function test_inventory_save_returns_its_selected_default_addons(): void
    {
        $section = InventorySection::create(['name' => 'خدمات', 'sort_order' => 1]);
        $main = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت اصلی', 'amount' => 100000, 'active' => true]);
        $addon = Inventory::create(['section_id' => $section->id, 'name' => 'کالای جانبی', 'amount' => 25000, 'active' => true]);

        $this->postJson('/api/inventory', [
            'sections' => [],
            'items' => [
                ['id' => $main->id, 'section_id' => $section->id, 'name' => $main->name, 'amount' => 100000, 'active' => true, 'default_addon_ids' => [$addon->id]],
                ['id' => $addon->id, 'section_id' => $section->id, 'name' => $addon->name, 'amount' => 25000, 'active' => true],
            ],
        ])->assertOk();

        $this->getJson('/api/inventory')
            ->assertOk()
            ->assertJsonPath('0.name', 'خدمت اصلی')
            ->assertJsonPath('0.default_addons.0.name', 'کالای جانبی');
    }

    public function test_addon_definitions_are_saved_with_inventory_fields_and_exposed_to_context(): void
    {
        $this->postJson('/api/inventory/addons', [
            'items' => [[
                'name' => 'ژل بی‌حسی', 'amount' => 120000, 'price' => 45000,
                'stock' => 18, 'min_stock' => 4, 'active' => true,
            ]],
        ])->assertOk();

        $this->getJson('/api/inventory/context')
            ->assertOk()
            ->assertJsonPath('addons.0.name', 'ژل بی‌حسی')
            ->assertJsonPath('addons.0.amount', '120000')
            ->assertJsonPath('addons.0.stock', 18);
    }

}
