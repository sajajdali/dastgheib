<?php

namespace App\Services\Assistant;

use App\Http\Requests\StoreDynamicReportRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\DynamicReportRowBuilder;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Reporting\DynamicReports\Sources\ReportSource;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * گزارش دلخواه روی همان موتور گزارش‌ساز: دستیار ستون و فیلتر را خودش انتخاب می‌کند،
 * پیش‌نمایش در چت نشان داده می‌شود و همان payload دکمهٔ «نمایش در گزارش‌ساز» را با فیلترهای یکسان باز می‌کند.
 */
final class AssistantReportBuilder
{
    public const TEXT_FIELDS = ['name', 'family', 'phone', 'nationalId', 'fileNo', 'city', 'referrer'];
    public const MULTI_FIELDS = ['status', 'doctor', 'source', 'work', 'section2', 'subsection', 'areas', 'extra', 'payment', 'account', 'finstatus'];
    private const DEFAULT_COLUMNS = [
        'patients' => ['fileNo', 'name', 'family', 'phone'],
        'appointments' => ['appointmentDate', 'name', 'family', 'phone', 'doctor', 'status'],
    ];

    public function __construct(
        private readonly DynamicReportRowBuilder $rows,
        private readonly DynamicReportFieldRegistry $fields,
    ) {}

    /** اجرای ابزار build_report. */
    public function tool(array $arguments): array
    {
        $payload = $this->payload($arguments);
        [$offset, $limit] = AssistantToolExecutor::window($arguments);
        $preview = $this->preview($payload, $offset, $limit);
        $labels = config('dynamic_reports.fields', []);
        $columns = collect($payload['columns'])->mapWithKeys(fn ($key) => [$key => $labels[$key] ?? $key])->all();
        $title = trim((string) ($arguments['title'] ?? '')) ?: ($payload['filters']['scope'] === 'patients' ? 'گزارش پرونده‌ها' : 'گزارش نوبت‌ها');

        return [
            'model' => [
                'count' => $preview['total'],
                'scope' => $payload['filters']['scope'],
                'columns' => array_values($columns),
                'rows' => $preview['rows'],
                'truncated' => $preview['total'] > $offset + count($preview['rows']),
                'report_builder_button' => true,
            ],
            'presentation' => [
                'type' => 'table',
                'title' => mb_substr($title, 0, 120),
                'count' => $preview['total'],
                'columns' => $columns,
                'rows' => $preview['rows'],
                'row_actions' => $preview['actions'],
                'report' => $payload,
                'report_label' => 'نمایش در گزارش‌ساز با همین فیلترها',
                'followups' => $this->followups($payload),
            ],
        ];
    }

    /** آرگومان‌های ابزار → payload دقیقاً هم‌شکل درخواست گزارش‌ساز. */
    public function payload(array $arguments): array
    {
        $scope = in_array($arguments['scope'] ?? '', ['patients', 'appointments'], true) ? $arguments['scope'] : 'patients';
        $available = array_keys(config('dynamic_reports.fields', []));
        $columns = array_values(array_unique(array_filter((array) ($arguments['columns'] ?? []), fn ($column) => in_array($column, $available, true))));
        if ($columns === []) $columns = self::DEFAULT_COLUMNS[$scope];

        $values = array_fill_keys(self::TEXT_FIELDS, '');
        $values['gender'] = ['male' => 'مرد', 'female' => 'زن'][$arguments['gender'] ?? 'all'] ?? 'همه';
        $values['noreturn'] = 'بدون محدودیت';
        $values['debt'] = '';
        $values['deposit'] = '';
        foreach ((array) ($arguments['text_filters'] ?? []) as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if (! in_array($field, self::TEXT_FIELDS, true)) continue;
            $value = trim((string) ($filter['value'] ?? ''));
            $values[$field] = match ($filter['mode'] ?? 'starts_with') {
                'has_value' => '*',
                'empty' => '-',
                'contains' => $value === '' ? '' : '*'.$value,
                default => $value,
            };
            if ($field === 'referrer' && in_array($values[$field], ['*', '-'], true)) $values[$field] = '';
        }
        $multi = [];
        foreach ((array) ($arguments['multi_filters'] ?? []) as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if (! in_array($field, self::MULTI_FIELDS, true)) continue;
            $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($filter['values'] ?? [])))));
            $multi[$field] = array_values(array_unique(array_map(fn ($value) => $this->resolveValue($field, $value), $values)));
        }
        $comparators = ['debt' => 'eq', 'deposit' => 'eq'];
        foreach ((array) ($arguments['money_filters'] ?? []) as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if (! in_array($field, ['debt', 'deposit'], true) || ! is_numeric($filter['value'] ?? null)) continue;
            $values[$field] = (string) (int) $filter['value'];
            $comparators[$field] = in_array($filter['op'] ?? '', ['lt', 'gt', 'eq'], true) ? $filter['op'] : 'eq';
        }
        $months = (int) ($arguments['no_return_months'] ?? 0);
        if ($months >= 1 && $months <= 6) $values['noreturn'] = strtr((string) $months, ['1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶']).' ماه';

        $today = (new JalaliMonthWindow)->date(now('Asia/Tehran'));
        $from = $this->date($arguments['from'] ?? null) ?? ($scope === 'patients' ? '1300-01-01' : substr($today, 0, 8).'01');
        $to = $this->date($arguments['to'] ?? null) ?? $today;

        return [
            'columns' => $columns,
            'filters' => [
                'scope' => $scope,
                'values' => $values,
                'multi' => $multi,
                'range' => ['amount' => [
                    'from' => is_numeric($arguments['amount_from'] ?? null) ? (string) (int) $arguments['amount_from'] : '',
                    'to' => is_numeric($arguments['amount_to'] ?? null) ? (string) (int) $arguments['amount_to'] : '',
                ]],
                'birthDate' => ['from' => $this->parts($this->date($arguments['birth_from'] ?? null)), 'to' => $this->parts($this->date($arguments['birth_to'] ?? null))],
                'reportDate' => ['from' => $this->parts($from), 'to' => $this->parts($to)],
                'comparators' => $comparators,
                'sort' => ($arguments['sort'] ?? 'oldest') === 'newest' ? 'desc' : 'asc',
            ],
        ];
    }

    /** مقدارهای واقعی قابل انتخاب هر فیلتر چندگزینه‌ای؛ null یعنی آزاد. */
    public function allowedValues(string $field): ?array
    {
        return match ($field) {
            'status' => config('dynamic_reports.appointment_statuses', []),
            'work' => ['انجام شد', 'انجام نشد', 'ترمیم', 'مشاوره'],
            'finstatus' => ['ضعیف', 'متوسط', 'خوب', 'عالی'],
            'doctor' => $this->cached('doctors', fn () => Doctor::query()->orderBy('name')->pluck('name')->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all()),
            'source' => $this->distinct('source'),
            'payment' => $this->distinct('payment_method'),
            'account' => $this->distinct('payment_account'),
            default => null,
        };
    }

    /** مقدار نزدیک به مقدار واقعی؛ اگر پیدا نشد خطایی با فهرست مقادیر مجاز تا مدل خودش اصلاح کند. */
    private function resolveValue(string $field, string $value): string
    {
        $allowed = $this->allowedValues($field);
        if ($allowed === null || in_array($value, $allowed, true)) return $value;
        $clean = fn (string $text) => preg_replace('/[\s\x{200C}]+/u', '', mb_strtolower($text));
        $matches = array_values(array_filter($allowed, fn ($option) => str_contains($clean($option), $clean($value)) || str_contains($clean($value), $clean($option))));
        if (count($matches) === 1) return $matches[0];
        $labels = config('dynamic_reports.fields', []);
        $hint = $field === 'finstatus' ? ' (وضعیت مالی رتبهٔ مالی بیمار است، نه پرداخت نوبت؛ برای نوبت پرداخت‌شده از amount_from=1 و بدهی برابر صفر یا فیلتر payment استفاده کن)' : '';
        throw new InvalidArgumentException("مقدار «{$value}» برای «".($labels[$field] ?? $field)."» معتبر نیست{$hint}. مقادیر مجاز: ".implode('، ', array_slice($allowed, 0, 40)));
    }

    private function distinct(string $column): array
    {
        return $this->cached($column, fn () => Appointment::query()->whereNotNull($column)->where($column, '<>', '')
            ->distinct()->orderBy($column)->limit(60)->pluck($column)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all());
    }

    private function cached(string $key, callable $resolve): array
    {
        $tenant = function_exists('tenant') && tenant() ? tenant()->getTenantKey() : 'central';

        try {
            return Cache::remember("assistant:report-values:{$tenant}:{$key}", 600, $resolve);
        } catch (\Throwable $exception) {
            report($exception);
            return [];
        }
    }

    /** پیش‌نمایش یک صفحه با همان منبع، فیلتر و ستون‌سازِ گزارش‌ساز. */
    public function preview(array $payload, int $offset, int $limit): array
    {
        $filters = $this->normalized($payload);
        $source = app(config('dynamic_reports.sources.'.$filters['scope'].'.class'));
        if (! $source instanceof ReportSource) throw new InvalidArgumentException('مبنای گزارش معتبر نیست.');
        $query = $source->query($filters);
        if (($filters['sort'] ?? 'asc') === 'desc') $query->reorder()->orderByDesc($query->getModel()->getQualifiedKeyName());
        $total = (clone $query)->count();
        $records = (clone $query)->offset($offset)->limit($limit)->get();
        $columns = array_values(array_intersect($payload['columns'], $this->fields->keys()));
        $rows = [];
        $actions = [];
        foreach ($source->rows($records, $filters) as $sourceRow) {
            $context = $sourceRow['context'];
            $rows[] = array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                array_intersect_key($this->rows->build($columns, $context), array_flip($columns)));
            $fileNumber = (string) ($context->patient->file_number ?? '');
            $actions[] = $fileNumber !== '' ? ['page' => 'patient_profile', 'file_number' => $fileNumber, 'label' => 'پرونده'] : null;
        }

        return ['total' => $total, 'rows' => $rows, 'actions' => $actions];
    }

    /** همان اعتبارسنجی و نرمال‌سازی درخواست گزارش‌ساز، تا تعداد چت و گزارش‌ساز یکی باشد. */
    private function normalized(array $payload): array
    {
        $request = StoreDynamicReportRequest::create('/', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        try {
            $request->validateResolved();
        } catch (ValidationException $exception) {
            throw new InvalidArgumentException(collect($exception->errors())->flatten()->first() ?: 'فیلترهای گزارش معتبر نیست.');
        }

        return $request->normalizedFilters();
    }

    /** پیشنهادهای گام بعد؛ فقط اگر کاربر بزند به AI می‌رود. */
    private function followups(array $payload): array
    {
        $filters = $payload['filters'];
        $columns = $payload['columns'];
        $items = [];
        if (! in_array('phone', $columns, true)) $items[] = 'همین گزارش را با شمارهٔ تماس نشان بده';
        if (($filters['values']['gender'] ?? 'همه') === 'همه') $items[] = 'همین را فقط برای خانم‌ها نشان بده';
        if ($filters['scope'] === 'patients' && ! in_array('city', $columns, true)) $items[] = 'ستون شهر را هم اضافه کن';
        if ($filters['scope'] === 'appointments' && empty($filters['multi']['status'])) $items[] = 'فقط نوبت‌هایی که «آمد» هستند';
        if ($filters['scope'] === 'patients' && ($filters['values']['noreturn'] ?? 'بدون محدودیت') === 'بدون محدودیت') $items[] = 'کدام‌ها ۳ ماه است برنگشته‌اند؟';

        return array_map(fn ($prompt) => ['label' => $prompt, 'prompt' => $prompt], array_slice($items, 0, 3));
    }

    private function date(mixed $value): ?string
    {
        $value = strtr(trim((string) $value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);

        return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) ? $value : null;
    }

    private function parts(?string $date): ?array
    {
        return $date === null ? null : array_map('intval', explode('-', $date));
    }
}
