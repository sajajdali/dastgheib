<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\InventorySection;
use App\Services\CampaignLeadSync;
use App\Services\CampaignRowMerger;
use App\Support\CampaignFollowupNotes;
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
        $sync = app(CampaignLeadSync::class);
        $visible = FollowupLeadPhoneVisibility::visibleConsultants($request);
        $wantFull = $request->boolean('full');
        $wantSummary = $request->boolean('summary');

        $campaigns->each(function (Campaign $campaign) use ($sync, $visible, $wantFull, $wantSummary): void {
            $sync->ensureSyncedFromPayload($campaign);
            $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];

            if ($wantFull) {
                if (is_array($payload['rows'] ?? null)) {
                    $payload['rows'] = CampaignFollowupNotes::normalizePayloadRows($payload['rows']);
                } else {
                    $payload['rows'] = $sync->rowsForCampaign($campaign->getKey());
                }
            } else {
                // فهرست سبک: ردیف‌ها را برنمی‌گردانیم؛ آمار از جدول ایندکس‌شده می‌آید.
                unset($payload['rows'], $payload['banners'], $payload['attachmentData']);
                if ($wantSummary) {
                    unset($payload['description']);
                }
            }

            $campaign->setAttribute('ui_payload', $payload);
            $campaign->setAttribute('lead_stats', $sync->statsForCampaign($campaign->getKey()));
            $campaign->setAttribute('rows_omitted', ! $wantFull);
            FollowupLeadPhoneVisibility::maskCampaign($campaign, $visible);
        });

        return $campaigns;
    }

    /**
     * صفحه‌بندی سرنخ‌های یک کمپین با فیلتر سروری.
     */
    public function leads(Request $request, Campaign $campaign)
    {
        $sync = app(CampaignLeadSync::class);
        $sync->ensureSyncedFromPayload($campaign);

        $filters = $request->only([
            'q',
            'gender',
            'consultant',
            'source',
            'status',
            'interest',
            'reason',
            'subsection',
            'contact_date_from',
            'contact_date_to',
            'follow_up_date_from',
            'follow_up_date_to',
        ]);

        $paginator = $sync->paginate(
            $campaign,
            $filters,
            max(1, (int) $request->input('page', 1)),
            max(1, (int) $request->input('per_page', CampaignLeadSync::DEFAULT_PER_PAGE)),
        );

        $visible = FollowupLeadPhoneVisibility::visibleConsultants($request);
        $rows = FollowupLeadPhoneVisibility::maskRows($paginator->items(), $visible);

        return response()->json([
            'data' => array_values($rows),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'lead_stats' => $sync->statsForCampaign($campaign->getKey()),
        ]);
    }

    /**
     * ذخیرهٔ جزئی سرنخ‌ها بدون ارسال کل تاریخچه (برای مجموعهٔ بزرگ).
     */
    public function syncLeads(Request $request, Campaign $campaign)
    {
        $request->validate([
            'upserts' => ['nullable', 'array'],
            'upserts.*.subsectionId' => ['nullable', 'integer'],
            'upserts.*.subsectionName' => ['nullable', 'string', 'max:255'],
            'deletes' => ['nullable', 'array'],
            'deletes.*' => ['string', 'max:64'],
        ]);

        // مثل ui_payload.rows: validated() بقیهٔ فیلدهای ردیف را حذف می‌کند.
        $upserts = array_values(array_filter((array) $request->input('upserts', []), 'is_array'));
        $deletes = array_values((array) $request->input('deletes', []));
        $serverPayload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        $serverRows = is_array($serverPayload['rows'] ?? null) ? $serverPayload['rows'] : [];
        $upserts = FollowupLeadPhoneVisibility::restoreMaskedPhones($upserts, $serverRows);
        $upserts = CampaignFollowupNotes::normalizePayloadRows($upserts, $request->user()?->name);

        $result = app(CampaignLeadSync::class)->applyBatch(
            $campaign,
            $upserts,
            $deletes,
        );

        $visible = FollowupLeadPhoneVisibility::visibleConsultants($request);

        return response()->json([
            'lead_stats' => $result['stats'],
            'upserted' => FollowupLeadPhoneVisibility::maskRows(
                array_values(array_filter(
                    $result['rows'],
                    fn ($row) => in_array((string) ($row['_localId'] ?? ''), array_map(
                        fn ($item) => (string) ($item['_localId'] ?? ''),
                        $upserts
                    ), true)
                )),
                $visible
            ),
            'deleted' => $deletes,
        ]);
    }

    /**
     * هشدار پیگیری امروز و عدم‌پیگیری‌ها بدون بارگذاری کل کمپین‌ها.
     */
    public function alerts(Request $request)
    {
        $sync = app(CampaignLeadSync::class);
        Campaign::query()->orderBy('id')->chunkById(50, function ($campaigns) use ($sync): void {
            foreach ($campaigns as $campaign) {
                $sync->ensureSyncedFromPayload($campaign);
            }
        });

        $alerts = $sync->alerts(now(), max(1, min(500, (int) $request->input('limit', 200))));
        $visible = FollowupLeadPhoneVisibility::visibleConsultants($request);
        $alerts['today'] = FollowupLeadPhoneVisibility::maskRows($alerts['today'], $visible);
        $alerts['missed'] = FollowupLeadPhoneVisibility::maskRows($alerts['missed'], $visible);

        return response()->json($alerts);
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
        if (! ($payload['isGeneralFollowup'] ?? false)) {
            $campaign->update(['ui_payload' => array_merge($payload, [
                'title' => 'پیگیری کلی',
                'isGeneralFollowup' => true,
            ])]);
        }

        $fresh = $campaign->fresh();
        app(CampaignLeadSync::class)->ensureSyncedFromPayload($fresh);

        return $this->forViewer($fresh, $request, lean: true);
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
            $data['ui_payload']['rows'] = CampaignFollowupNotes::normalizePayloadRows(
                $data['ui_payload']['rows'],
                $request->user()?->name
            );
        }

        $campaign = Campaign::create($data);
        if (is_array($data['ui_payload']['rows'] ?? null)) {
            app(CampaignLeadSync::class)->replaceAll($campaign, $data['ui_payload']['rows']);
        }

        return response()->json($this->forViewer($campaign->fresh(), $request), 201);
    }

    /**
     * اگر base_rows فرستاده شود، ردیف‌ها با نسخهٔ فعلی سرور ادغام می‌شوند تا
     * تغییرات هم‌زمان کاربرهای دیگر پاک نشود. تداخل روی یک فیلد از یک ردیف = 409.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $data = $this->data($request);
        $request->validate(['base_rows' => ['nullable', 'array']]);
        $sync = app(CampaignLeadSync::class);

        if (! $request->exists('base_rows') || ! is_array($data['ui_payload']['rows'] ?? null)) {
            $rowsProvided = is_array($data['ui_payload'] ?? null) && array_key_exists('rows', $data['ui_payload']);
            if ($rowsProvided) {
                $serverPayload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                $data['ui_payload']['rows'] = FollowupLeadPhoneVisibility::restoreMaskedPhones($data['ui_payload']['rows'], $serverPayload['rows'] ?? []);
                $data['ui_payload']['rows'] = CampaignFollowupNotes::normalizePayloadRows(
                    $data['ui_payload']['rows'],
                    $request->user()?->name
                );
            } elseif (is_array($data['ui_payload'] ?? null)) {
                // ویرایش متادیتای کمپین بدون ارسال ردیف‌ها؛ سرنخ‌های قبلی پاک نمی‌شوند.
                $serverPayload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                $data['ui_payload']['rows'] = is_array($serverPayload['rows'] ?? null) ? $serverPayload['rows'] : [];
            }
            $campaign->update($data);
            if ($rowsProvided) {
                $sync->replaceAll($campaign, $data['ui_payload']['rows']);
            }

            return $this->forViewer($campaign->fresh(), $request, lean: ! $rowsProvided);
        }

        return DB::transaction(function () use ($request, $campaign, $data, $sync) {
            $current = Campaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            $serverPayload = is_array($current->ui_payload) ? $current->ui_payload : [];
            $serverRows = is_array($serverPayload['rows'] ?? null) ? $serverPayload['rows'] : [];
            if ($serverRows === []) {
                $sync->ensureSyncedFromPayload($current);
                $serverRows = $sync->rowsForCampaign($current->getKey());
            }
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

            $data['ui_payload']['rows'] = CampaignFollowupNotes::normalizePayloadRows(
                $result['rows'],
                $request->user()?->name
            );
            $current->update($data);
            $sync->replaceAll($current, $data['ui_payload']['rows']);

            return $this->forViewer($current->fresh(), $request);
        });
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->noContent();
    }

    private function forViewer(Campaign $campaign, Request $request, bool $lean = false): Campaign
    {
        $sync = app(CampaignLeadSync::class);
        $sync->ensureSyncedFromPayload($campaign);
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];

        if ($lean) {
            unset($payload['rows'], $payload['banners'], $payload['attachmentData']);
            $campaign->setAttribute('rows_omitted', true);
        } elseif (is_array($payload['rows'] ?? null)) {
            $payload['rows'] = CampaignFollowupNotes::normalizePayloadRows($payload['rows']);
            $campaign->setAttribute('rows_omitted', false);
        } else {
            $payload['rows'] = $sync->rowsForCampaign($campaign->getKey());
            $campaign->setAttribute('rows_omitted', false);
        }

        $campaign->setAttribute('ui_payload', $payload);
        $campaign->setAttribute('lead_stats', $sync->statsForCampaign($campaign->getKey()));

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
