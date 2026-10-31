<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoipIncomingCallService;
use App\Support\VoipIntegration;
use App\Support\PatientPhoneVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoipCallEventController extends Controller
{
    public function receive(Request $request, VoipIncomingCallService $calls): JsonResponse
    {
        $settings = VoipIntegration::settings();
        abort_unless($settings['enabled'] && $settings['incoming_calls_enabled'], 403, 'نمایش تماس‌های ورودی برای این مطب غیرفعال است.');

        $raw = $request->getContent();
        abort_if(strlen($raw) > 65536, 413, 'بدنهٔ رویداد بیش از حد بزرگ است.');
        $event = json_decode($raw, true);
        abort_unless(is_array($event), 422, 'JSON نامعتبر است.');
        if (! in_array($event['event'] ?? null, VoipIncomingCallService::EVENTS, true)) {
            return response()->json(['ok' => true]);
        }

        $event = Validator::make($event, [
            'event' => ['required', 'string'],
            'eventId' => ['required', 'uuid'],
            'callId' => ['required', 'string', 'max:100'],
            'seq' => ['required', 'integer', 'min:1'],
            'occurredAt' => ['required', 'date'],
            'test' => ['sometimes', 'boolean'],
            'data' => ['required', 'array'],
        ])->validate();
        $calls->record($event, $raw);

        return response()->json(['ok' => true]);
    }

    public function index(VoipIncomingCallService $calls): JsonResponse
    {
        return response()->json(['calls' => $calls->recent()]);
    }

    public function live(Request $request, VoipIncomingCallService $calls): JsonResponse
    {
        abort_unless($request->user()?->can('patients.view') && PatientPhoneVisibility::canView($request), 403);
        $settings = VoipIntegration::settings();
        $enabled = $settings['enabled'] && $settings['incoming_calls_enabled'];

        return response()->json(['enabled' => $enabled, 'calls' => $enabled ? $calls->live() : []]);
    }
}
