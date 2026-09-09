<?php

namespace Tests\Unit;

use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class AppointmentRegistrationMetaTest extends TestCase
{
    public function test_batch_save_array_can_be_decorated_with_registration_meta(): void
    {
        $method = new ReflectionMethod(AppointmentController::class, 'withPatientHistoryRegistrationMeta');

        $result = $method->invoke(app(AppointmentController::class), []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }
}
