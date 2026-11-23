<?php

namespace Tests\Unit;

use App\Models\InventorySection;
use App\Support\AppointmentPhotosRequirement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentPhotosRequirementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('photos_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function test_laser_only_appointment_skips_photos_when_section_disables_them(): void
    {
        $laser = InventorySection::create(['name' => 'لیزر', 'photos_required' => false]);

        $this->assertFalse(AppointmentPhotosRequirement::forServices([
            ['name' => 'لیزر فول بادی', 'section_id' => $laser->id],
        ]));
    }

    public function test_mixed_services_still_require_photos_when_any_section_needs_them(): void
    {
        $laser = InventorySection::create(['name' => 'لیزر', 'photos_required' => false]);
        $gel = InventorySection::create(['name' => 'ژل', 'photos_required' => true]);

        $this->assertTrue(AppointmentPhotosRequirement::forServices([
            ['name' => 'لیزر', 'section_id' => $laser->id],
            ['name' => 'ژل لب', 'section_id' => $gel->id],
        ]));
    }

    public function test_empty_services_default_to_required(): void
    {
        $this->assertTrue(AppointmentPhotosRequirement::forServices([]));
    }
}
