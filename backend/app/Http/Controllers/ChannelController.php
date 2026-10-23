<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $channelsData = $request->validate([
            '*' => ['array'],
            '*.id' => ['nullable', 'integer'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.icon' => ['nullable', 'string', 'max:30'],
            '*.exclude_doctor_commission' => ['sometimes', 'boolean'],
        ]);

        $savedChannels = [];
        $changedCommissionSources = [];
        foreach ($channelsData as $channel) {
            $model = !empty($channel['id']) ? Channel::find($channel['id']) : null;
            $previousName = trim((string) ($model?->name ?? ''));
            $previousRule = (bool) ($model?->exclude_doctor_commission ?? false);
            $model ??= new Channel();
            $model->fill([
                'name' => trim($channel['name']),
                'icon' => $channel['icon'] ?? null,
                'exclude_doctor_commission' => (bool) ($channel['exclude_doctor_commission'] ?? false),
            ])->save();
            if ($model->wasRecentlyCreated && (bool) $model->exclude_doctor_commission) {
                // A recreated channel may match historical appointment source
                // text, so the rule must apply to those rows immediately too.
                $changedCommissionSources[] = $model->name;
            } elseif ($model->wasRecentlyCreated === false && (
                $previousName !== $model->name
                || $previousRule !== (bool) $model->exclude_doctor_commission
            )) {
                $changedCommissionSources[] = $previousName;
                $changedCommissionSources[] = $model->name;
            }
            $savedChannels[] = $model->fresh();
        }

        if ($changedCommissionSources !== []) {
            app(AppointmentController::class)->resyncResourceEarningsForSources($changedCommissionSources);
        }

        return response()->json($savedChannels);
    }

    public function uploadIcon(Request $request, Channel $channel)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($channel->icon_image_path) {
            Storage::disk('public')->delete($channel->icon_image_path);
        }
        $path = $request->file('image')->store("channels/{$channel->id}", 'public');
        $channel->update(['icon_image_path' => $path]);

        return response()->json(['channel' => $channel->fresh()]);
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

        $source = $channel->name;
        if ($channel->icon_image_path) {
            Storage::disk('public')->delete($channel->icon_image_path);
        }
        $channel->delete();
        app(AppointmentController::class)->resyncResourceEarningsForSources([$source]);
        return response()->json(null, 204);
    }
}
