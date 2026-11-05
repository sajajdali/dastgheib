<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

    /** فهرست کاربران با وضعیت نمایش تماس ورودی برای هر کدام (پیش‌فرض روشن). */
    public function incomingCallUsers(): JsonResponse
    {
        $disabled = VoipIntegration::incomingDisabledUserIds();

        return response()->json([
            'users' => User::query()->orderBy('name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname ?? '',
                    'avatar_url' => $user->avatar_url,
                    'access_blocked' => (bool) ($user->access_blocked ?? false),
                    'can_receive' => $user->can('patients.view'),
                    'phone_hidden' => ! ($user->can('patients.view_phone') && ! $user->can('patients.hide_phone')),
                    'incoming_calls_enabled' => ! in_array((int) $user->id, $disabled, true),
                ])->values(),
        ]);
    }

    public function updateIncomingCallUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        VoipIntegration::setIncomingCallsForUser((int) $user->id, (bool) $data['enabled']);

        return response()->json([
            'message' => $data['enabled']
                ? "نمایش تماس ورودی برای «{$user->name}» فعال شد."
                : "نمایش تماس ورودی برای «{$user->name}» غیرفعال شد.",
            'user_id' => $user->id,
            'incoming_calls_enabled' => (bool) $data['enabled'],
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
