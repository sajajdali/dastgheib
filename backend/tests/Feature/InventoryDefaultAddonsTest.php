<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventorySection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('color')->nullable();
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
        Schema::create('inventory_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->unique();
            $table->timestamps();
        });
        Schema::create('inventory_booking_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->foreignId('doctor_id')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_booking_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_resource_id');
            $table->timestamps();
        });
        Schema::create('inventory_booking_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_id');
            $table->timestamps();
        });
        Schema::create('inventory_booking_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->timestamps();
        });
        Schema::create('inventory_booking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
            $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table) { $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps(); });
    }

    public function test_inventory_save_returns_its_selected_default_addons(): void
    {
        $root = InventorySection::create(['name' => 'خدمات', 'sort_order' => 1]);
        $section = InventorySection::create(['parent_id' => $root->id, 'level' => 2, 'name' => 'پوست', 'sort_order' => 1]);
        $main = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت اصلی', 'amount' => 100000, 'active' => true]);
        $addon = Inventory::create(['section_id' => $section->id, 'name' => 'کالای جانبی', 'amount' => 25000, 'active' => true]);

        $this->postJson('/api/inventory', [
            'sections' => [],
            'items' => [
                ['id' => $main->id, 'save_version' => $main->fresh()->save_version, 'section_id' => $section->id, 'name' => $main->name, 'amount' => 100000, 'active' => true, 'default_addon_ids' => [$addon->id]],
                ['id' => $addon->id, 'save_version' => $addon->fresh()->save_version, 'section_id' => $section->id, 'name' => $addon->name, 'amount' => 25000, 'active' => true],
            ],
        ])->assertOk();

        $this->getJson('/api/inventory')
            ->assertOk()
            ->assertJsonPath('0.name', 'خدمت اصلی')
            ->assertJsonPath('0.default_addons.0.name', 'کالای جانبی');
    }

    public function test_inventory_cannot_be_saved_directly_in_a_root_section(): void
    {
        $root = InventorySection::create(['name' => 'خدمات', 'sort_order' => 1]);

        $this->postJson('/api/inventory', [
            'sections' => [],
            'items' => [[
                'section_id' => $root->id,
                'name' => 'خدمت نامعتبر',
                'amount' => 100000,
                'active' => true,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.section_id']);

        $this->assertDatabaseMissing('inventories', ['name' => 'خدمت نامعتبر']);
    }

    public function test_adding_a_service_does_not_modify_existing_services_or_sections(): void
    {
        $root = InventorySection::create(['name' => 'پوست و زیبایی', 'level' => 1]);
        $child = InventorySection::create(['name' => 'ژل', 'parent_id' => $root->id, 'level' => 1]);
        $grandchild = InventorySection::create(['name' => 'خدمات ژل', 'parent_id' => $child->id, 'level' => 1]);
        $sibling = InventorySection::create(['name' => 'بوتاکس', 'parent_id' => $root->id, 'level' => 1]);

        $existing = Inventory::create(['section_id' => $grandchild->id, 'name' => 'خدمت قبلی', 'amount' => 100000, 'active' => true]);
        DB::table('inventory_booking_settings')->insert(['inventory_id' => $existing->id]);
        $updatedAt = $existing->updated_at;

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->postJson('/api/inventory', [
                'sections' => [
                    ['client_id' => 'new-section', 'parent_id' => $root->id, 'name' => 'خدمات جدید'],
                ],
                'items' => [['client_id' => 'new-service', 'section_id' => 'new-section', 'name' => 'خدمت جدید', 'amount' => 250000]],
            ])->assertOk();

            $mutations = collect(DB::getQueryLog())
                ->filter(fn ($query) => preg_match('/^delete\s+/i', $query['query']))
                ->values();
            $this->assertCount(0, $mutations);
            $this->assertDatabaseHas('inventories', ['id' => $existing->id, 'name' => 'خدمت قبلی', 'section_id' => $grandchild->id]);
            $this->assertDatabaseHas('inventory_booking_settings', ['inventory_id' => $existing->id]);
            $this->assertEquals($updatedAt, $existing->fresh()->updated_at);
            $this->assertDatabaseHas('inventory_sections', ['id' => $sibling->id]);
            $this->assertDatabaseHas('inventories', ['name' => 'خدمت جدید']);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
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

    public function test_adding_an_addon_preserves_existing_addon_and_its_assignment(): void
    {
        $section = InventorySection::create(['name' => 'ژل']);
        $inventory = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت']);
        $existing = \App\Models\InventoryAddonDefinition::create(['name' => 'جانبی قبلی', 'amount' => 100]);
        $inventory->addonDefinitions()->attach($existing->id);

        $this->postJson('/api/inventory/addons', ['items' => [
            ['name' => 'جانبی جدید', 'amount' => 200],
        ]])->assertOk();

        $this->assertDatabaseHas('inventory_addon_definitions', ['id' => $existing->id, 'name' => 'جانبی قبلی']);
        $this->assertDatabaseHas('inventory_addon_assignments', ['inventory_id' => $inventory->id, 'inventory_addon_definition_id' => $existing->id]);
        $this->assertDatabaseHas('inventory_addon_definitions', ['name' => 'جانبی جدید']);
    }

    public function test_explicit_delete_refuses_a_service_with_booking_history(): void
    {
        $root = InventorySection::create(['name' => 'خدمات']);
        $section = InventorySection::create(['parent_id' => $root->id, 'level' => 2, 'name' => 'ژل']);
        $existing = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت دارای سابقه']);
        Schema::create('appointment_service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id');
        });
        DB::table('appointment_service_bookings')->insert(['inventory_id' => $existing->id]);

        $this->postJson('/api/inventory', [
            'items' => [['section_id' => $section->id, 'name' => 'خدمت جدید']],
            'deleted_item_ids' => [$existing->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas('inventories', ['id' => $existing->id]);
        $this->assertDatabaseMissing('inventories', ['name' => 'خدمت جدید']);
    }

    public function test_editing_one_field_keeps_the_service_id_stock_and_booking_settings(): void
    {
        $root = InventorySection::create(['name' => 'خدمات']);
        $section = InventorySection::create(['parent_id' => $root->id, 'level' => 2, 'name' => 'ژل']);
        $existing = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت قبلی', 'stock' => 17, 'amount' => 100]);
        DB::table('inventory_booking_settings')->insert(['inventory_id' => $existing->id]);

        $this->postJson('/api/inventory', [
            'items' => [['id' => $existing->id, 'save_version' => $existing->fresh()->save_version, 'name' => 'نام تازه']],
        ])->assertOk();

        $this->assertDatabaseHas('inventories', [
            'id' => $existing->id, 'name' => 'نام تازه', 'stock' => 17, 'amount' => 100,
        ]);
        $this->assertDatabaseHas('inventory_booking_settings', ['inventory_id' => $existing->id]);
    }

    public function test_stale_service_edit_is_rejected_without_overwriting_newer_data(): void
    {
        $root = InventorySection::create(['name' => 'خدمات']);
        $section = InventorySection::create(['parent_id' => $root->id, 'level' => 2, 'name' => 'ژل']);
        $existing = Inventory::create(['section_id' => $section->id, 'name' => 'خدمت', 'stock' => 10]);

        $this->postJson('/api/inventory', [
            'items' => [['id' => $existing->id, 'save_version' => 'stale', 'name' => 'نام قدیمی']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas('inventories', ['id' => $existing->id, 'name' => 'خدمت', 'stock' => 10]);
    }

    public function test_explicit_section_delete_processes_children_before_parent(): void
    {
        $root = InventorySection::create(['name' => 'ریشه']);
        $child = InventorySection::create(['parent_id' => $root->id, 'level' => 2, 'name' => 'فرزند']);
        $grandchild = InventorySection::create(['parent_id' => $child->id, 'level' => 3, 'name' => 'نوه']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->postJson('/api/inventory', [
                'deleted_section_ids' => [$root->id, $child->id, $grandchild->id],
            ])->assertOk();

            $deletedIds = collect(DB::getQueryLog())
                ->filter(fn ($query) => str_starts_with(strtolower($query['query']), 'delete from "inventory_sections"'))
                ->map(fn ($query) => (int) $query['bindings'][0])->values()->all();
            $this->assertSame([$grandchild->id, $child->id, $root->id], $deletedIds);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

}
