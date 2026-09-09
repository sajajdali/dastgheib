<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PatientController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PatientBirthDateNormalizationTest extends TestCase
{
    public function test_it_converts_the_reported_jalali_birth_date_for_mysql(): void
    {
        $method = new ReflectionMethod(PatientController::class, 'normalizeBirthDateForStorage');

        $this->assertSame('1992-05-20', $method->invoke(new PatientController, '۱۳۷۱-۰۲-۳۰'));
    }

    public function test_it_keeps_a_valid_gregorian_birth_date(): void
    {
        $method = new ReflectionMethod(PatientController::class, 'normalizeBirthDateForStorage');

        $this->assertSame('1992-05-20', $method->invoke(new PatientController, '1992-05-20'));
    }

    public function test_it_preserves_empty_optional_dates_as_null(): void
    {
        $request = Request::create('/', 'POST', [
            'birth_date' => null,
            'marriage_date' => '',
        ]);
        $method = new ReflectionMethod(PatientController::class, 'normalizePatientRequestDigits');

        $method->invoke(new PatientController, $request);

        $this->assertNull($request->input('birth_date'));
        $this->assertNull($request->input('marriage_date'));
    }
}
