<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\InventorySection;
use App\Services\CampaignRowMerger;
use App\Support\FollowupLeadPhoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    private const GENERAL_FOLLOWUP_MARKER = '__system_general_followups__';

    public function index(Request $request)
    {
        $campaigns = Campaign::query()->orderByDesc('active')->orderBy('name')->get();
        $visible = FollowupLeadPhoneVisibility::visibleConsultants($request);
        $campaigns->each(fn (Campaign $campaign) => FollowupLeadPhoneVisibility::maskCampaign($campaign, $visible));

        // نسخهٔ سبک برای فید «سرنخ‌ها»: بنرها و فایل‌های پیوست حذف می‌شوند.
        if ($request->boolean('summary')) {
            $campaigns->each(function (Campaign $campaign): void {
                $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                unset($payload['banners'], $payload['attachmentData']);
                $campaign->setAttribute('ui_payload', $payload);
            });
        }

        return $campaigns;
    }

    public function general(Request $request)
    {
        $campaign = Campaign::query()->firstOrCreate(
            ['note' => self::GENERAL_FOLLOWUP_MARKER],
            [
                'name' => 'پیگیری کلی',
                'budget' => 0,
                'active' => true,
                'ui_payload' => [
                    'title' => 'پیگیری کلی',
                    'source' => '',
                    'sourceName' => '',
                    'date' => '',
                    'cost' => '',
                    'description' => '',
                    'banners' => [],
                    'campaignStatus' => 'active',
                    'pinned' => false,
                    'isGeneralFollowup' => true,
                    'rows' => [],
                ],
            ]
        );

        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        if (!($payload['isGeneralFollowup'] ?? false)) {
            $campaign->update(['ui_payload' => array_merge($payload, [
                'title' => 'پیگیری کلی',
                'isGeneralFollowup' => true,
            ])]);
        }

        return $this->forViewer($campaign->fresh(), $request);
    }

    /**
     * فقط زیربخش‌های آخر (برگ‌های درخت بخش‌ها) برای ستون «بخش» پیگیری کلی؛
     * بخش اصلی و بخشی که زیربخش ندارد در فهرست نمی‌آید.
     */
    public function subsections()
    {
        return InventorySection::query()
            ->whereNotNull('parent_id')
            ->whereDoesntHave('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (InventorySection $section) => ['id' => $section->id, 'name' => trim((string) $section->name)])
            ->values();
    }

    /**
     * بنر کمپین به‌صورت فایل ذخیره می‌شود و فقط نشانی‌اش در ui_payload می‌ماند؛
     * عکس base64 داخل JSON کمپین هم از حد بستهٔ MySQL رد می‌شد و هم با هر ویرایش سرنخ دوباره ارسال می‌شد.
     */
    public function uploadBanner(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ], [
            'image.max' => 'حجم بنر بعد از فشرده‌سازی نباید بیشتر از ۲ مگابایت باشد.',
            'image.image' => 'فایل انتخاب‌شده تصویر نیست.',
            'image.mimes' => 'فقط تصویر JPG، PNG، WEBP یا GIF قابل بارگذاری است.',
        ]);

        $file = $request->file('image');
        $path = $file->store('campaign-banners', 'public');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => '/tenant-files/'.ltrim($path, '/'),
        ], 201);
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        if (is_array($data['ui_payload']['rows'] ?? null)) {
            $data['ui_payload']['rows'] = app(CampaignRowMerger::class)->withIds($data['ui_payload']['rows']);
        }

        return response()->json($this->forViewer(Campaign::create($data), $request), 201);
    }

    /**
     * اگر base_rows فرستاده شود، ردیف‌ها با نسخهٔ فعلی سرور ادغام می‌شوند تا
     * تغییرات هم‌زمان کاربرهای دیگر پاک نشود. تداخل روی یک فیلد از یک ردیف = 409.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $data = $this->data($request);
        $request->validate(['base_rows' => ['nullable', 'array']]);

        if (! $request->exists('base_rows') || ! is_array($data['ui_payload']['rows'] ?? null)) {
            if (is_array($data['ui_payload']['rows'] ?? null)) {
                $serverPayload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                $data['ui_payload']['rows'] = FollowupLeadPhoneVisibility::restoreMaskedPhones($data['ui_payload']['rows'], $serverPayload['rows'] ?? []);
            }
            $campaign->update($data);

            return $this->forViewer($campaign->fresh(), $request);
        }

        return DB::transaction(function () use ($request, $campaign, $data) {
            $current = Campaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            $serverPayload = is_array($current->ui_payload) ? $current->ui_payload : [];
            $serverRows = is_array($serverPayload['rows'] ?? null) ? $serverPayload['rows'] : [];
            // کاربری که شمارهٔ بعضی ردیف‌ها را ماسک‌شده دیده، همان را برمی‌گرداند؛ پیش از ادغام بازیابی می‌شود.
            $result = app(CampaignRowMerger::class)->merge(
                FollowupLeadPhoneVisibility::restoreMaskedPhones((array) $request->input('base_rows', []), $serverRows),
                $serverRows,
                FollowupLeadPhoneVisibility::restoreMaskedPhones($data['ui_payload']['rows'], $serverRows),
            );

            if ($result['conflicts']) {
                return response()->json([
                    'message' => 'این سرنخ هم‌زمان توسط کاربر دیگری تغییر کرده است.',
                    'conflicts' => $result['conflicts'],
                    'campaign' => $this->forViewer($current, $request),
                ], 409);
            }

            $data['ui_payload']['rows'] = $result['rows'];
            $current->update($data);

            return $this->forViewer($current->fresh(), $request);
        });
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->noContent();
    }

    private function forViewer(Campaign $campaign, Request $request): Campaign
    {
        return FollowupLeadPhoneVisibility::maskCampaign($campaign, FollowupLeadPhoneVisibility::visibleConsultants($request));
    }

    private function data(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel_id' => ['nullable', 'exists:channels,id'],
            'starts_on' => ['nullable', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'ends_on' => ['nullable', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'active' => ['boolean'],
            'ui_payload' => ['nullable', 'array'],
            'ui_payload.rows' => ['nullable', 'array'],
            'ui_payload.rows.*.subsectionId' => ['nullable', 'integer'],
            'ui_payload.rows.*.subsectionName' => ['nullable', 'string', 'max:255'],
        ]);

        // قواعد تو در توی ردیف‌ها فقط اعتبارسنجی می‌کنند؛ validated() بقیهٔ فیلدهای ردیف را حذف می‌کند،
        // پس ui_payload کامل همان‌طور که فرستاده شده ذخیره می‌شود.
        if ($request->exists('ui_payload')) {
            $data['ui_payload'] = $request->input('ui_payload');
        }

        return $data;
    }
}
