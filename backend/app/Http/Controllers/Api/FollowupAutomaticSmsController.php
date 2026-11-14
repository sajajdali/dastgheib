<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FollowupAutomaticSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** فعال‌سازی سناریوی پیامک اتوماتیک از پیگیری و خاموش کردن آن هنگام ثبت نوبت. */
class FollowupAutomaticSmsController extends Controller
{
    public function __construct(private readonly FollowupAutomaticSmsService $service) {}

    public function scenarios(): JsonResponse
    {
        return response()->json(['scenarios' => $this->service->activeScenarios()]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['campaign_id' => ['nullable', 'integer']]);
        return response()->json(['rows' => (object) $this->service->forCampaign($data['campaign_id'] ?? null)]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['nullable', 'integer'],
            'row_key' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'scenario_ids' => ['present', 'array'],
            'scenario_ids.*' => ['integer'],
        ]);
        if (\App\Support\PatientPhoneVisibility::looksMasked($data['phone'])) {
            return response()->json(['message' => 'شمارهٔ این مراجع برای شما مخفی است؛ سناریوی پیامک فقط توسط مشاور همین ردیف فعال می‌شود.'], 422);
        }

        return response()->json(['enrollments' => $this->service->syncRow(
            $data['campaign_id'] ?? null, $data['row_key'], $data['phone'], $data['patient_name'] ?? null, $data['scenario_ids'], $request->user()
        )]);
    }

    public function person(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = FollowupAutomaticSmsService::normalizePhone($data['phone']);
        return response()->json(['enrollments' => $this->service->pendingForPhones(collect([$phone]))->get($phone, [])]);
    }

    public function toggle(Request $request, string $enrollmentKey): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        return response()->json(['enrollment' => $this->service->toggle($enrollmentKey, (bool) $data['enabled'], $request->user())]);
    }
}
