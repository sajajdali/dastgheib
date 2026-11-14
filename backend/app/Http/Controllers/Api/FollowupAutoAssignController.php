<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * کلید «تقسیم خودکار شماره‌ها بین مشاوران» در پیگیری‌ها.
 * وقتی روشن است، شماره‌هایی که در جدول کمپین paste می‌شوند به‌ترتیب بین مشاوران انتخاب‌شده
 * تقسیم می‌شوند و ترتیب از آخرین مشاورِ کمپین قبلی ادامه پیدا می‌کند.
 */
class FollowupAutoAssignController extends Controller
{
    public const SETTING_KEY = 'followup_auto_assign';

    public function show()
    {
        return response()->json($this->settings());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'staff_ids' => ['present', 'array', 'max:200'],
            'staff_ids.*' => ['integer', 'distinct', 'exists:staff,id'],
        ]);
        if ($data['enabled'] && count($data['staff_ids']) === 0) {
            return response()->json(['message' => 'برای تقسیم خودکار حداقل یک مشاور انتخاب کنید.'], 422);
        }

        AppSetting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => json_encode([
            'enabled' => (bool) $data['enabled'],
            'staff_ids' => array_values(array_map('intval', $data['staff_ids'])),
        ])]);

        return response()->json($this->settings());
    }

    private function settings(): array
    {
        $raw = json_decode((string) AppSetting::getByKey(self::SETTING_KEY, '{}'), true);
        $raw = is_array($raw) ? $raw : [];
        // مشاوری که حذف شده از ترتیب بیرون می‌رود.
        $existing = Staff::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $staffIds = array_values(array_filter(array_map('intval', $raw['staff_ids'] ?? []), fn ($id) => in_array($id, $existing, true)));

        return ['enabled' => (bool) ($raw['enabled'] ?? false) && $staffIds !== [], 'staff_ids' => $staffIds];
    }
}
