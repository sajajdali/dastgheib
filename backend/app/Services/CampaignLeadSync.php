<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Support\CampaignFollowupNotes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * نگه‌داری سرنخ‌های پیگیری در جدول ایندکس‌شده و صفحه‌بندی/آمار سبک برای API.
 */
class CampaignLeadSync
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 200;

    /**
     * @param  array<int, mixed>  $rows
     */
    public function replaceAll(Campaign $campaign, array $rows): void
    {
        $prepared = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = app(FollowupSectionTree::class)->enrichLeadRow(CampaignFollowupNotes::normalizeRow($row));
            $localId = trim((string) ($normalized['_localId'] ?? ''));
            if ($localId === '') {
                $localId = 'srv-'.Str::uuid()->toString();
                $normalized['_localId'] = $localId;
            }

            $prepared[] = $this->attributesFromRow($campaign->getKey(), $normalized, $index);
        }

        DB::transaction(function () use ($campaign, $prepared): void {
            CampaignLead::query()->where('campaign_id', $campaign->getKey())->delete();
            foreach (array_chunk($prepared, 200) as $chunk) {
                CampaignLead::query()->insert(array_map(function (array $row): array {
                    if (is_array($row['payload'] ?? null)) {
                        $row['payload'] = json_encode($row['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    foreach (['created_at', 'updated_at'] as $stamp) {
                        if (isset($row[$stamp]) && ! is_string($row[$stamp])) {
                            $row[$stamp] = (string) $row[$stamp];
                        }
                    }

                    return $row;
                }, $chunk));
            }
            $this->mirrorLeadsIntoPayload($campaign);
        });
    }

    /**
     * به‌روزرسانی/درج چند ردیف بدون پاک کردن بقیه؛ برای ذخیرهٔ صفحه‌ای فرانت.
     *
     * @param  array<int, array>  $upserts
     * @param  array<int, string>  $deleteLocalIds
     * @return array{rows: array<int, array>, stats: array<string, int|float>}
     */
    public function applyBatch(Campaign $campaign, array $upserts, array $deleteLocalIds = []): array
    {
        $deleteLocalIds = array_values(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $deleteLocalIds
        )));

        DB::transaction(function () use ($campaign, $upserts, $deleteLocalIds): void {
            if ($deleteLocalIds !== []) {
                CampaignLead::query()
                    ->where('campaign_id', $campaign->getKey())
                    ->whereIn('local_id', $deleteLocalIds)
                    ->delete();
            }

            $maxSort = (int) CampaignLead::query()
                ->where('campaign_id', $campaign->getKey())
                ->max('sort_order');

            foreach ($upserts as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized = app(FollowupSectionTree::class)->enrichLeadRow(CampaignFollowupNotes::normalizeRow($row));
                $localId = trim((string) ($normalized['_localId'] ?? ''));
                if ($localId === '') {
                    $localId = 'srv-'.Str::uuid()->toString();
                    $normalized['_localId'] = $localId;
                }

                $existing = CampaignLead::query()
                    ->where('campaign_id', $campaign->getKey())
                    ->where('local_id', $localId)
                    ->first();

                $sort = $existing?->sort_order;
                if ($sort === null) {
                    $maxSort++;
                    $sort = $maxSort;
                }

                $attrs = $this->attributesFromRow($campaign->getKey(), $normalized, (int) $sort);
                unset($attrs['created_at']);
                if ($existing) {
                    $existing->fill($attrs)->save();
                } else {
                    CampaignLead::query()->create($attrs);
                }
            }

            $this->mirrorLeadsIntoPayload($campaign);
        });

        $campaign->refresh();

        return [
            'rows' => $this->rowsForCampaign($campaign->getKey()),
            'stats' => $this->statsForCampaign($campaign->getKey()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(Campaign $campaign, array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $page = max(1, $page);

        $paginator = $this->filteredQuery($campaign->getKey(), $filters)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (CampaignLead $lead) => $this->rowFromLead($lead))
        );

        return $paginator;
    }

    /**
     * @return array<string, int|float>
     */
    public function statsForCampaign(int|string $campaignId): array
    {
        $base = CampaignLead::query()
            ->where('campaign_id', $campaignId)
            ->where('meaningful', true);

        $total = (clone $base)->count();
        $answered = (clone $base)->where('status', 'پاسخ داد')->count();
        $noAnswer = (clone $base)->where('status', 'پاسخ نداد')->count();
        $uncalled = (clone $base)->where(function (Builder $q): void {
            $q->whereNull('status')->orWhere('status', '');
        })->count();
        $interest1 = (clone $base)->where('interest', '1')->count();
        $interest2 = (clone $base)->where('interest', '2')->count();
        $interest3 = (clone $base)->where('interest', '3')->count();
        $appointment = (clone $base)->where('interest', 'ok')->count();
        $workDone = (clone $base)->where('appointment_done', true)->count();
        $today = $this->todayQuery($campaignId)->count();
        $missed = $this->missedQuery($campaignId)->count();

        $scoreRows = (clone $base)
            ->where(function (Builder $q): void {
                $q->whereNotNull('status')->where('status', '!=', '')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('interest')->where('interest', '!=', '');
                    });
            })
            ->get(['status', 'interest']);

        $score = 0;
        if ($scoreRows->isNotEmpty()) {
            $sum = 0;
            foreach ($scoreRows as $row) {
                $sum += $this->rowScore((string) $row->status, (string) $row->interest);
            }
            $score = (int) max(0, min(100, round($sum / $scoreRows->count())));
        }

        return [
            'total' => $total,
            'answered' => $answered,
            'no_answer' => $noAnswer,
            'uncalled' => $uncalled,
            'interest_1' => $interest1,
            'interest_2' => $interest2,
            'interest_3' => $interest3,
            'appointment' => $appointment,
            'work_done' => $workDone,
            'today' => $today,
            'missed' => $missed,
            'score' => $score,
        ];
    }

    /**
     * @return array<int, array<string, int|float>>
     */
    public function statsByCampaignIds(array $campaignIds): array
    {
        $out = [];
        foreach ($campaignIds as $id) {
            $out[(string) $id] = $this->statsForCampaign($id);
        }

        return $out;
    }

    /**
     * @return array{today: array<int, array>, missed: array<int, array>}
     */
    public function alerts(Carbon|string|null $today = null, int $limit = 200): array
    {
        $today = $this->normalizeDate($today ?? now());
        $campaignMeta = Campaign::query()
            ->get(['id', 'name', 'active', 'ui_payload', 'note'])
            ->keyBy('id');

        $todayItems = [];
        foreach (
            CampaignLead::query()
                ->where('meaningful', true)
                ->where('follow_up_date', $today)
                ->where('appointment_registered', false)
                ->where(function (Builder $q): void {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['پاسخ داد', 'اشتباه']);
                })
                ->orderBy('campaign_id')
                ->orderBy('sort_order')
                ->limit($limit)
                ->get() as $lead
        ) {
            $campaign = $campaignMeta->get($lead->campaign_id);
            if (! $campaign || ! $this->isActiveCampaign($campaign)) {
                continue;
            }
            $todayItems[] = $this->alertItem($lead, $campaign);
        }

        $missedItems = [];
        foreach (
            CampaignLead::query()
                ->where('meaningful', true)
                ->whereNotNull('follow_up_date')
                ->where('follow_up_date', '!=', '')
                ->where('follow_up_date', '<', $today)
                ->where('appointment_registered', false)
                ->orderBy('follow_up_date')
                ->orderBy('campaign_id')
                ->limit($limit)
                ->get() as $lead
        ) {
            $campaign = $campaignMeta->get($lead->campaign_id);
            if (! $campaign || ! $this->isActiveCampaign($campaign)) {
                continue;
            }
            $missedItems[] = $this->alertItem($lead, $campaign);
        }

        return ['today' => $todayItems, 'missed' => $missedItems];
    }

    /**
     * @return array<int, array>
     */
    public function rowsForCampaign(int|string $campaignId): array
    {
        return CampaignLead::query()
            ->where('campaign_id', $campaignId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CampaignLead $lead) => $this->rowFromLead($lead))
            ->all();
    }

    public function ensureSyncedFromPayload(Campaign $campaign): void
    {
        $hasLeads = CampaignLead::query()->where('campaign_id', $campaign->getKey())->exists();
        if ($hasLeads) {
            return;
        }
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        if ($rows === []) {
            return;
        }
        $this->replaceAll($campaign, $rows);
    }

    /**
     * ui_payload.rows را با جدول همگام نگه می‌دارد تا گزارش‌های قدیمی نشکنند.
     */
    public function mirrorLeadsIntoPayload(Campaign $campaign): void
    {
        $rows = $this->rowsForCampaign($campaign->getKey());
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        $payload['rows'] = $rows;
        $campaign->ui_payload = $payload;
        $campaign->save();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(int|string $campaignId, array $filters): Builder
    {
        $query = CampaignLead::query()->where('campaign_id', $campaignId);

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('consultant', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('subsection_name', 'like', $like)
                    ->orWhere('payload->description', 'like', $like);
            });
        }

        foreach (['gender', 'consultant', 'source', 'status', 'interest', 'reason'] as $field) {
            $values = $this->stringList($filters[$field] ?? null);
            if ($values === []) {
                continue;
            }
            $query->whereIn($field, $values);
        }

        $subsections = $this->stringList($filters['subsection'] ?? null);
        if ($subsections !== []) {
            $tree = app(FollowupSectionTree::class);
            $query->where(function (Builder $builder) use ($subsections, $tree): void {
                foreach ($subsections as $value) {
                    if ($value === '__none__') {
                        $builder->orWhere(function (Builder $inner): void {
                            $inner->whereNull('subsection_id')
                                ->where(function (Builder $emptyName): void {
                                    $emptyName->whereNull('subsection_name')->orWhere('subsection_name', '');
                                });
                        });

                        continue;
                    }

                    if (preg_match('/^service:(\d+)$/', $value, $match)) {
                        $serviceId = (int) $match[1];
                        $builder->orWhere(function (Builder $inner) use ($serviceId): void {
                            $inner->where('payload->serviceId', $serviceId)
                                ->orWhere('payload->service_id', $serviceId);
                        });

                        continue;
                    }

                    $sectionId = null;
                    if (preg_match('/^section:(\d+)$/', $value, $match)) {
                        $sectionId = (int) $match[1];
                    } elseif (ctype_digit($value)) {
                        $sectionId = (int) $value;
                    }

                    if ($sectionId) {
                        $ids = $tree->descendantSectionIds($sectionId);
                        $builder->orWhere(function (Builder $inner) use ($ids, $sectionId): void {
                            $inner->whereIn('subsection_id', $ids);
                            foreach ($ids as $id) {
                                $inner->orWhereJsonContains('payload->sectionPathIds', $id);
                            }
                            $inner->orWhere('payload->rootSectionId', $sectionId);
                        });

                        continue;
                    }

                    // سازگاری با فیلتر قدیمی مبتنی بر نام برگ
                    $builder->orWhere('subsection_name', $value);
                }
            });
        }

        $contactFrom = $this->normalizeDate($filters['contact_date_from'] ?? null);
        $contactTo = $this->normalizeDate($filters['contact_date_to'] ?? null);
        if ($contactFrom) {
            $query->where('contact_date', '>=', $contactFrom);
        }
        if ($contactTo) {
            $query->where('contact_date', '<=', $contactTo);
        }

        $followFrom = $this->normalizeDate($filters['follow_up_date_from'] ?? null);
        $followTo = $this->normalizeDate($filters['follow_up_date_to'] ?? null);
        if ($followFrom) {
            $query->where('follow_up_date', '>=', $followFrom);
        }
        if ($followTo) {
            $query->where('follow_up_date', '<=', $followTo);
        }

        return $query;
    }

    private function todayQuery(int|string $campaignId): Builder
    {
        $today = $this->normalizeDate(now());

        return CampaignLead::query()
            ->where('campaign_id', $campaignId)
            ->where('meaningful', true)
            ->where('follow_up_date', $today)
            ->where('appointment_registered', false)
            ->where(function (Builder $q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['پاسخ داد', 'اشتباه']);
            });
    }

    private function missedQuery(int|string $campaignId): Builder
    {
        $today = $this->normalizeDate(now());

        return CampaignLead::query()
            ->where('campaign_id', $campaignId)
            ->where('meaningful', true)
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '!=', '')
            ->where('follow_up_date', '<', $today)
            ->where('appointment_registered', false);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFromRow(int|string $campaignId, array $row, int $sortOrder): array
    {
        $localId = (string) $row['_localId'];
        $status = trim((string) ($row['status'] ?? ''));
        $interest = $this->normalizeInterest($row['interest'] ?? null);
        $payload = $row;
        unset($payload['_localId']);

        $subsectionId = isset($row['subsectionId']) && $row['subsectionId'] !== '' && $row['subsectionId'] !== null
            ? (int) $row['subsectionId']
            : null;
        $pathIds = is_array($row['sectionPathIds'] ?? null)
            ? array_values(array_map('intval', $row['sectionPathIds']))
            : [];
        if ($subsectionId === null && $pathIds !== []) {
            $subsectionId = (int) end($pathIds);
        }

        $now = now();

        return [
            'campaign_id' => $campaignId,
            'local_id' => $localId,
            'sort_order' => $sortOrder,
            'full_name' => $this->nullableString($row['fullName'] ?? null),
            'phone' => $this->nullableString($row['phone'] ?? null),
            'gender' => $this->nullableString($row['gender'] ?? null),
            'consultant' => $this->nullableString($row['consultant'] ?? null),
            'source' => $this->nullableString($row['source'] ?? null),
            'status' => $status !== '' ? $status : null,
            'interest' => $interest !== '' ? $interest : null,
            'reason' => $this->nullableString($row['reason'] ?? null),
            'contact_date' => $this->normalizeDate($row['contactDate'] ?? null),
            'contact_time' => $this->nullableString($row['contactTime'] ?? null),
            'follow_up_date' => $this->normalizeDate($row['followUpDate'] ?? null),
            'subsection_id' => $subsectionId,
            'subsection_name' => $this->nullableString($row['subsectionName'] ?? null),
            'appointment_registered' => (bool) ($row['appointmentRegistered'] ?? false),
            'appointment_done' => $this->isAppointmentDone($row),
            'meaningful' => $this->isMeaningful($row),
            'payload' => $payload,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromLead(CampaignLead $lead): array
    {
        $payload = is_array($lead->payload) ? $lead->payload : [];
        $row = array_merge($payload, [
            '_localId' => $lead->local_id,
            'fullName' => $lead->full_name ?? ($payload['fullName'] ?? ''),
            'phone' => $lead->phone ?? ($payload['phone'] ?? ''),
            'gender' => $lead->gender ?? ($payload['gender'] ?? ''),
            'consultant' => $lead->consultant ?? ($payload['consultant'] ?? ''),
            'source' => $lead->source ?? ($payload['source'] ?? ''),
            'status' => $lead->status ?? ($payload['status'] ?? ''),
            'interest' => $lead->interest ?? ($payload['interest'] ?? ''),
            'reason' => $lead->reason ?? ($payload['reason'] ?? ''),
            'contactDate' => $lead->contact_date ?? ($payload['contactDate'] ?? ''),
            'contactTime' => $lead->contact_time ?? ($payload['contactTime'] ?? ''),
            'followUpDate' => $lead->follow_up_date ?? ($payload['followUpDate'] ?? ''),
            'subsectionId' => $lead->subsection_id,
            'subsectionName' => $lead->subsection_name ?? ($payload['subsectionName'] ?? ''),
            'rootSectionId' => $payload['rootSectionId'] ?? null,
            'sectionPathIds' => is_array($payload['sectionPathIds'] ?? null) ? $payload['sectionPathIds'] : [],
            'sectionPath' => $payload['sectionPath'] ?? '',
            'serviceId' => $payload['serviceId'] ?? null,
            'serviceName' => $payload['serviceName'] ?? '',
            'appointmentRegistered' => (bool) $lead->appointment_registered,
        ]);

        return app(FollowupSectionTree::class)->enrichLeadRow(CampaignFollowupNotes::normalizeRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function alertItem(CampaignLead $lead, Campaign $campaign): array
    {
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];

        return [
            '_localId' => $lead->local_id,
            'fullName' => $lead->full_name ?: '',
            'phone' => $lead->phone ?: '',
            'consultant' => $lead->consultant ?: '',
            'followUpDate' => $lead->follow_up_date ?: '',
            'status' => $lead->status ?: '',
            'campaignId' => $campaign->getKey(),
            'campaignTitle' => trim((string) ($payload['title'] ?? $campaign->name ?? '')),
        ];
    }

    private function isActiveCampaign(Campaign $campaign): bool
    {
        $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
        $status = strtolower(trim((string) ($payload['campaignStatus'] ?? ($campaign->active ? 'active' : 'archived'))));

        return ! in_array($status, ['archived', 'archive', 'آرشیو', 'آرشیو شده', 'آرشیوشده'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isMeaningful(array $row): bool
    {
        if (CampaignFollowupNotes::rowHasDescription($row)) {
            return true;
        }

        foreach (['fullName', 'phone', 'contactDate', 'followUpDate', 'gender', 'consultant', 'source', 'status', 'interest', 'reason'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isAppointmentDone(array $row): bool
    {
        $done = $row['appointmentDone'] ?? $row['appointment_done'] ?? $row['workDone'] ?? false;
        if (is_bool($done)) {
            return $done;
        }

        return in_array(strtolower(trim((string) $done)), ['1', 'true', 'yes', 'done', 'انجام شد'], true);
    }

    private function rowScore(string $status, string $interest): int
    {
        $score = 0;
        $status = trim($status);
        $interest = $this->normalizeInterest($interest);

        if ($status === 'پاسخ داد') {
            $score += 20;
        } elseif ($status === 'پیگیری') {
            $score += 10;
        } elseif ($status === 'اشتباه') {
            $score -= 5;
        }

        if ($interest === '1') {
            $score += 15;
        } elseif ($interest === '2') {
            $score += 35;
        } elseif ($interest === '3') {
            $score += 60;
        } elseif ($interest === 'ok') {
            $score += 80;
        }

        return $score;
    }

    private function normalizeInterest(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['ok', 'وقت', 'وقت داده شد'], true)) {
            return 'ok';
        }

        return $value;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (! is_array($value)) {
            $value = [$value];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $value
        ), fn ($item) => $item !== ''));
    }
}
