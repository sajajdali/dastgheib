<?php

namespace Tests\Unit;

use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\InventorySection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AppointmentHistorySectionsTest extends TestCase
{
    public function test_history_includes_every_booked_section_and_subsection_without_duplicates(): void
    {
        Schema::create('inventory_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $right = InventorySection::create(['name' => 'بخش اول']);
        $rightOne = InventorySection::create(['name' => 'زیربخش اول', 'parent_id' => $right->id]);
        $rightTwo = InventorySection::create(['name' => 'زیربخش دوم', 'parent_id' => $right->id]);
        $left = InventorySection::create(['name' => 'بخش دوم']);
        $leftOne = InventorySection::create(['name' => 'زیربخش سوم', 'parent_id' => $left->id]);

        $appointment = new Appointment([
            'services' => [
                ['name' => 'خدمت ۱', 'section_id' => $rightOne->id],
                ['name' => 'خدمت ۲', 'section_id' => $rightTwo->id],
                ['name' => 'خدمت ۳', 'section_id' => $rightOne->id],
                ['name' => 'خدمت ۴', 'section_id' => $leftOne->id],
            ],
            'service_types' => [$right->id, $left->id],
        ]);

        $method = new ReflectionMethod(AppointmentController::class, 'withPatientHistorySections');
        $result = $method->invoke(app(AppointmentController::class), collect([$appointment]));

        $this->assertSame([
            ['section' => 'بخش اول', 'subsection' => 'زیربخش اول'],
            ['section' => 'بخش اول', 'subsection' => 'زیربخش دوم'],
            ['section' => 'بخش دوم', 'subsection' => 'زیربخش سوم'],
        ], $result->first()->history_section_paths);
    }
}
