<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\VoipIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → «ارتباط با VoIP»: switch the call-center lookup on and make its token. */
class VoipIntegrationController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['voip' => VoipIntegration::settings()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $voip = VoipIntegration::setEnabled((bool) $data['enabled']);

        return response()->json([
            'message' => $voip['enabled'] ? 'ارتباط با VoIP فعال شد.' : 'ارتباط با VoIP غیرفعال شد.',
            'voip' => $voip,
        ]);
    }

    public function generate(): JsonResponse
    {
        $hadToken = VoipIntegration::settings()['token'] !== '';

        return response()->json([
            'message' => $hadToken ? 'توکن جدید ساخته شد؛ توکن قبلی دیگر کار نمی‌کند.' : 'توکن ساخته شد.',
            'voip' => VoipIntegration::generate(),
        ]);
    }

    public function updateIncomingCalls(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $voip = VoipIntegration::setIncomingCallsEnabled((bool) $data['enabled']);

        return response()->json([
            'message' => $voip['incoming_calls_enabled'] ? 'نمایش تماس‌های ورودی فعال شد.' : 'نمایش تماس‌های ورودی غیرفعال شد.',
            'voip' => $voip,
        ]);
    }
}
