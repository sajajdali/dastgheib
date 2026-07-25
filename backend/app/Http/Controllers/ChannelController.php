<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    /**
     * نمایش تمام کانال‌ها
     */
    public function index()
    {
        $channels = Channel::all();
        return response()->json($channels);
    }

    /**
     * ذخیره کانال‌های جدید یا بروزرسانی گروهی (اگر نیاز به ذخیره چند کانال در یک درخواست باشد)
     */
    public function store(Request $request)
    {
        // داده ارسالی باید آرایه‌ای از کانال‌ها (با نام کانال باشد) باشد.
        $channelsData = $request->all();

        // اعتبارسنجی ساده روی هر آیتم
        foreach ($channelsData as $channel) {
            if (!isset($channel['name']) || trim($channel['name']) === '') {
                return response()->json(['error' => 'فیلد نام کانال الزامی است.'], 422);
            }
            if (isset($channel['icon']) && mb_strlen($channel['icon']) > 30) {
                return response()->json(['error' => 'آیکون کانال نامعتبر است.'], 422);
            }
        }

        // حذف کل کانال‌های قبلی (اگر می‌خواهید جایگزین شوند)
        Channel::truncate();

        // درج داده‌های جدید
        foreach ($channelsData as $channel) {
            Channel::create([
                'name' => $channel['name'],
                'icon' => $channel['icon'] ?? null,
            ]);
        }

        $newChannels = Channel::all();
        return response()->json($newChannels, 201);
    }

    /**
     * حذف یک کانال مشخص با شناسه
     */
    public function destroy($id)
    {
        $channel = Channel::find($id);

        if (!$channel) {
            return response()->json(['error' => 'کانال یافت نشد.'], 404);
        }

        $channel->delete();
        return response()->json(null, 204);
    }
}
