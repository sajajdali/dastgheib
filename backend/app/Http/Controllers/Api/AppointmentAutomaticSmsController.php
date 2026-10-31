<?php

namespace App\Http\Controllers\Api;

use App\Events\AppointmentChanged;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentAutomaticSms;
use App\Services\AppointmentAutomaticSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentAutomaticSmsController extends Controller
{
    public function update(
        Request $request,
        Appointment $appointment,
        AppointmentAutomaticSms $automaticSms,
        AppointmentAutomaticSmsService $service,
    ): JsonResponse {
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $item = $service->toggle($appointment, $automaticSms, (bool) $data['is_enabled']);
        $service->decorateAppointments(collect([$appointment]));
        event(AppointmentChanged::fromAppointment($appointment, 'automatic_sms_updated'));

        return response()->json([
            'item' => $item,
            'automatic_sms' => $appointment->getAttribute('automatic_sms'),
        ]);
    }
}
