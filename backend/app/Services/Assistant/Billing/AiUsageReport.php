<?php

namespace App\Services\Assistant\Billing;

use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * گزارش مصرف از جدول مرکزی central_ai_usage.
 * $filters: tenant_id، user_id (اجباری برای کاربر عادی)، user_ids، models، types، statuses، from، to، month.
 * در حالت tenant فقط مبلغ کسرشده (charge) نمایش داده می‌شود؛ قیمت تمام‌شده و سود فقط برای پنل مرکزی است.
 */
final class AiUsageReport
{
    public const GROUPS = [
        'day' => 'jalali_date', 'week' => 'jalali_week', 'month' => 'jalali_month',
        'user' => 'user_id', 'model' => 'model', 'type' => 'type',
        'conversation' => 'conversation_id', 'tenant' => 'tenant_id',
    ];
    public const TIME_GROUPS = ['day', 'week', 'month'];
    public const TYPES = ['chat' => 'پاسخ گفت‌وگو', 'tool_round' => 'جست‌وجو با ابزار', 'transcription' => 'رونویسی صوت'];
    public const STATUSES = ['completed' => 'کامل', 'incomplete' => 'ناتمام'];

    public function __construct(private readonly bool $central = false) {}

    public static function central(): self
    {
        return new self(true);
    }

    /** کارت‌های امروز، دیروز، این هفته (از شنبه)، این ماه و ماه قبل شمسی. */
    public function periods(array $scope): array
    {
        $jalali = new JalaliMonthWindow;
        $now = now('Asia/Tehran');
        $today = $jalali->date($now);
        $yesterday = $jalali->date($now->copy()->subDay());
        $weekStart = $jalali->date($now->copy()->subDays(($now->dayOfWeek + 1) % 7));
        $year = (int) substr($today, 0, 4);
        $month = (int) substr($today, 5, 2);
        $lastMonth = $month === 1 ? sprintf('%04d-12', $year - 1) : sprintf('%04d-%02d', $year, $month - 1);
        $periods = [
            'today' => ['label' => 'امروز', 'from' => $today, 'to' => $today],
            'yesterday' => ['label' => 'دیروز', 'from' => $yesterday, 'to' => $yesterday],
            'this_week' => ['label' => 'این هفته', 'from' => $weekStart, 'to' => $today],
            'this_month' => ['label' => 'این ماه', 'from' => substr($today, 0, 7).'-01', 'to' => $today],
            'last_month' => ['label' => 'ماه قبل', 'from' => $lastMonth.'-01', 'to' => $lastMonth.'-31'],
        ];

        return collect($periods)->map(fn (array $period, string $key) => [
            'key' => $key, ...$period,
            ...$this->totals([...$scope, 'from' => $period['from'], 'to' => $period['to']]),
        ])->values()->all();
    }

    public function totals(array $filters): array
    {
        return $this->metrics($this->aggregate($this->query($filters))->first());
    }

    public function grouped(array $filters, string $groupBy, ?string $thenBy = null): array
    {
        $column = self::GROUPS[$groupBy];
        $parents = $this->aggregate($this->query($filters))->addSelect(DB::raw("{$column} as group_key"))
            ->when($groupBy === 'user', fn ($q) => $q->addSelect(DB::raw('MAX(user_name) as group_name')))
            ->groupBy($column);
        $parents = in_array($groupBy, self::TIME_GROUPS, true)
            ? $parents->orderBy($column)
            : $parents->orderByDesc('charge_micros')->limit(200);
        $rows = $parents->get();

        $children = collect();
        if ($thenBy && $thenBy !== $groupBy) {
            $childColumn = self::GROUPS[$thenBy];
            $children = $this->aggregate($this->query($filters))
                ->addSelect(DB::raw("{$column} as parent_key"), DB::raw("{$childColumn} as group_key"))
                ->when($thenBy === 'user', fn ($q) => $q->addSelect(DB::raw('MAX(user_name) as group_name')))
                ->groupBy($column, $childColumn)
                ->orderByDesc('charge_micros')
                ->get()->groupBy(fn ($row) => (string) $row->parent_key);
        }
        $labels = $this->labels($groupBy, $rows);
        $childLabels = $thenBy ? $this->labels($thenBy, $children->flatten(1)) : [];
        $total = max(1, (int) $rows->sum('charge_micros'));

        return [
            'group_by' => $groupBy,
            'then_by' => $thenBy,
            'rows' => $rows->map(fn ($row) => [
                'key' => $row->group_key,
                'label' => $labels[(string) $row->group_key] ?? (string) $row->group_key,
                ...$this->metrics($row),
                'share' => round(((int) $row->charge_micros) / $total * 100, 2),
                'children' => ($children[(string) $row->group_key] ?? collect())->map(fn ($child) => [
                    'key' => $child->group_key,
                    'label' => $childLabels[(string) $child->group_key] ?? (string) $child->group_key,
                    ...$this->metrics($child),
                ])->values()->all(),
            ])->values()->all(),
            'totals' => $this->totals($filters),
        ];
    }

    public function requests(array $filters, int $page = 1, int $perPage = 50): array
    {
        $paginator = $this->query($filters)->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());
        $models = config('assistant.models', []);

        return [
            'data' => $rows->map(fn ($row) => $this->requestRow($row, $models))->all(),
            'meta' => ['page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage()],
        ];
    }

    /** برای خروجی CSV؛ ردیف‌ها را دسته‌ای می‌خواند. */
    public function each(array $filters, callable $callback): void
    {
        $models = config('assistant.models', []);
        $this->query($filters)->orderBy('id')->chunk(500, function ($rows) use ($callback, $models): void {
            foreach ($rows as $row) $callback($this->requestRow($row, $models));
        });
    }

    public function filterOptions(array $filters): array
    {
        $base = $this->query(array_intersect_key($filters, array_flip(['tenant_id', 'user_id'])));

        return [
            'users' => (clone $base)->whereNotNull('user_id')->select('user_id', DB::raw('MAX(user_name) as name'))
                ->groupBy('user_id')->orderBy('name')->get()->map(fn ($row) => ['id' => (int) $row->user_id, 'name' => $row->name ?: "کاربر #{$row->user_id}"])->all(),
            // ترتیب ثابت کاتالوگ تا رنگ هر مدل در نمودار با تغییر فیلتر عوض نشود.
            'models' => collect([...array_keys(config('assistant.models', [])), (string) config('assistant.transcription_model')])
                ->merge((clone $base)->distinct()->orderBy('model')->pluck('model'))->unique()->values()
                ->map(fn ($model) => ['id' => $model, 'label' => $this->modelLabel($model)])->all(),
            'types' => collect(self::TYPES)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all(),
        ];
    }

    private function query(array $filters): Builder
    {
        $query = $this->connection()->table('central_ai_usage');
        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['user_id'])) $query->where('user_id', (int) $filters['user_id']);
        if (! empty($filters['user_ids'])) $query->whereIn('user_id', array_map('intval', (array) $filters['user_ids']));
        if (! empty($filters['models'])) $query->whereIn('model', (array) $filters['models']);
        if (! empty($filters['types'])) $query->whereIn('type', (array) $filters['types']);
        if (! empty($filters['statuses'])) $query->whereIn('status', (array) $filters['statuses']);
        if (! empty($filters['conversation_id'])) $query->where('conversation_id', (int) $filters['conversation_id']);
        if (! empty($filters['request_group'])) $query->where('request_group', $filters['request_group']);
        if (! empty($filters['from'])) $query->where('jalali_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->where('jalali_date', '<=', $filters['to']);
        if (! empty($filters['month'])) $query->where('jalali_month', $filters['month']);

        return $query;
    }

    private function aggregate(Builder $query): Builder
    {
        return $query->select([
            DB::raw('COUNT(*) as calls'),
            DB::raw('COUNT(DISTINCT request_group) as questions'),
            DB::raw('COALESCE(SUM(input_tokens), 0) as input_tokens'),
            DB::raw('COALESCE(SUM(cached_input_tokens), 0) as cached_input_tokens'),
            DB::raw('COALESCE(SUM(output_tokens), 0) as output_tokens'),
            DB::raw('COALESCE(SUM(reasoning_tokens), 0) as reasoning_tokens'),
            DB::raw('COALESCE(SUM(audio_input_tokens), 0) as audio_input_tokens'),
            DB::raw('COALESCE(SUM(audio_seconds), 0) as audio_seconds'),
            DB::raw('COALESCE(SUM(cost_micros), 0) as cost_micros'),
            DB::raw('COALESCE(SUM(charge_micros), 0) as charge_micros'),
            DB::raw('COALESCE(SUM(uncollected_micros), 0) as uncollected_micros'),
        ]);
    }

    private function metrics(?object $row): array
    {
        $questions = (int) ($row->questions ?? 0);
        $charge = (int) ($row->charge_micros ?? 0);
        $input = (int) ($row->input_tokens ?? 0);
        $metrics = [
            'calls' => (int) ($row->calls ?? 0),
            'questions' => $questions,
            'input_tokens' => $input,
            'cached_input_tokens' => (int) ($row->cached_input_tokens ?? 0),
            'cache_rate' => $input > 0 ? round(((int) ($row->cached_input_tokens ?? 0)) / $input * 100, 1) : 0,
            'output_tokens' => (int) ($row->output_tokens ?? 0),
            'reasoning_tokens' => (int) ($row->reasoning_tokens ?? 0),
            'audio_input_tokens' => (int) ($row->audio_input_tokens ?? 0),
            'audio_seconds' => round((float) ($row->audio_seconds ?? 0), 1),
            'charge_usd' => self::usd($charge),
            'avg_per_question_usd' => $questions > 0 ? self::usd(intdiv($charge, $questions)) : 0,
        ];
        if ($this->central) {
            $cost = (int) ($row->cost_micros ?? 0);
            $metrics['cost_usd'] = self::usd($cost);
            $metrics['profit_usd'] = self::usd($charge - $cost);
            $metrics['uncollected_usd'] = self::usd((int) ($row->uncollected_micros ?? 0));
        }

        return $metrics;
    }

    private function requestRow(object $row, array $models): array
    {
        $data = [
            'id' => (int) $row->id,
            'created_at' => (string) $row->created_at,
            'jalali_date' => $row->jalali_date,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'user_name' => $row->user_name,
            'model' => $row->model,
            'model_label' => $models[$row->model] ?? $row->model,
            'type' => $row->type,
            'type_label' => self::TYPES[$row->type] ?? $row->type,
            'round' => (int) $row->round,
            'request_group' => $row->request_group,
            'conversation_id' => $row->conversation_id !== null ? (int) $row->conversation_id : null,
            'input_tokens' => (int) $row->input_tokens,
            'cached_input_tokens' => (int) $row->cached_input_tokens,
            'output_tokens' => (int) $row->output_tokens,
            'reasoning_tokens' => (int) $row->reasoning_tokens,
            'audio_input_tokens' => (int) $row->audio_input_tokens,
            'audio_seconds' => $row->audio_seconds !== null ? (float) $row->audio_seconds : null,
            'charge_usd' => self::usd((int) $row->charge_micros),
            'status' => $row->status,
            'status_label' => self::STATUSES[$row->status] ?? $row->status,
            'estimated' => (bool) $row->estimated,
        ];
        if ($this->central) {
            $data['tenant_id'] = $row->tenant_id;
            $data['cost_usd'] = self::usd((int) $row->cost_micros);
            $data['markup_multiplier'] = (float) $row->markup_multiplier;
            $data['uncollected_usd'] = self::usd((int) $row->uncollected_micros);
            $data['response_id'] = $row->response_id;
        }

        return $data;
    }

    private function labels(string $groupBy, $rows): array
    {
        $keys = collect($rows)->pluck('group_key')->filter(fn ($key) => $key !== null)->map(fn ($key) => (string) $key)->unique()->values();
        return match ($groupBy) {
            'user' => collect($rows)->mapWithKeys(fn ($row) => [(string) $row->group_key => $row->group_name ?: ($row->group_key ? "کاربر #{$row->group_key}" : 'نامشخص')])->all(),
            'model' => $keys->mapWithKeys(fn ($key) => [$key => $this->modelLabel($key)])->all(),
            'type' => $keys->mapWithKeys(fn ($key) => [$key => self::TYPES[$key] ?? $key])->all(),
            'tenant' => $this->tenantNames($keys->all()),
            'conversation' => $this->conversationTitles($keys->all()),
            default => [],
        };
    }

    private function modelLabel(string $model): string
    {
        $label = config('assistant.models.'.$model);
        if ($model === config('assistant.transcription_model')) $label = 'رونویسی صوت';

        return $label ? "{$model} · {$label}" : $model;
    }

    private function tenantNames(array $ids): array
    {
        if ($ids === []) return [];

        return \App\Models\Tenant::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($tenant) => [(string) $tenant->getTenantKey() => (string) ($tenant->name ?? $tenant->getTenantKey())])->all();
    }

    private function conversationTitles(array $ids): array
    {
        $ids = array_values(array_filter($ids, fn ($id) => $id !== ''));
        $titles = collect($ids)->mapWithKeys(fn ($id) => [$id => "گفت‌وگوی #{$id}"])->all();
        if ($this->central || $ids === [] || ! (function_exists('tenant') && tenant())) return $titles;
        foreach (\App\Models\AiAssistantConversation::query()->whereIn('id', $ids)->get(['id', 'title']) as $row) {
            if ($row->title) $titles[(string) $row->id] = $row->title;
        }

        return $titles;
    }

    public static function usd(int $micros): float
    {
        return round($micros / 1000000, 6);
    }

    private function connection()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
