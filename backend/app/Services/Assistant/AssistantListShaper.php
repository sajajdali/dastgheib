<?php

namespace App\Services\Assistant;

use App\Models\User;
use InvalidArgumentException;

/**
 * الگوی مشترک همهٔ فهرست‌های دستیار:
 * - فهرست کوچک مستقیم نمایش داده می‌شود؛
 * - فهرست بزرگ کارت پرسش می‌گیرد («N مورد داریم؛ چطور نشان بدهم؟») و دکمه‌هایش بدون AI صفحه‌ها را می‌آورند؛
 * - مدل برای فهرست بزرگ فقط تعداد و چند نمونه می‌بیند تا توکن الکی مصرف نشود.
 */
final class AssistantListShaper
{
    public const LIST_TOOLS = [
        'find_doctors', 'list_appointments', 'list_service_followups', 'list_followups', 'search_patients',
        'search_people', 'preview_doctor_patients', 'find_patient_photos', 'query_clinic_records', 'get_doctor_revenue', 'build_report',
    ];
    private const ROW_KEYS = ['rows', 'doctors', 'patients'];
    private const MODEL_SAMPLE = 5;
    public const MAX_PAGE = 100;

    public function __construct(private readonly AssistantToolExecutor $executor) {}

    public function shape(string $tool, array $arguments, array $result): array
    {
        $presentation = $result['presentation'] ?? null;
        if (! in_array($tool, self::LIST_TOOLS, true) || ($presentation['type'] ?? null) !== 'table') return $result;
        $total = (int) ($presentation['count'] ?? count($presentation['rows'] ?? []));
        $threshold = (int) config('assistant.list_ask_threshold', 30);
        $presentation['list'] = [
            'tool' => $tool,
            'arguments' => array_diff_key($arguments, ['offset' => true, 'limit' => true]),
            'total' => $total,
            'loaded' => count($presentation['rows'] ?? []),
        ];
        if ($total > $threshold) {
            $presentation['ask'] = true;
            $presentation['suggestions'] = $this->suggestions($tool, $arguments);
            $model = $result['model'];
            foreach (self::ROW_KEYS as $key) {
                if (is_array($model[$key] ?? null)) $model[$key] = array_slice($model[$key], 0, self::MODEL_SAMPLE);
            }
            $model['rows_in_card'] = true;
            $model['note'] = "فهرست کامل {$total} موردی در کارت تعاملی به کاربر نشان داده شده و خودش انتخاب می‌کند چند مورد ببیند. ردیف‌ها را بازنویسی نکن؛ فقط تعداد را در یک جملهٔ کوتاه بگو.";
            $result['model'] = $model;
        }
        $result['presentation'] = $presentation;

        return $result;
    }

    /** صفحهٔ بعدی یک فهرست، مستقیم از سرور و بدون فراخوانی AI. مجوزها در اجرای دوبارهٔ ابزار بررسی می‌شوند. */
    public function page(User $user, string $tool, array $arguments, int $offset, int $limit): array
    {
        if (! in_array($tool, self::LIST_TOOLS, true)) throw new InvalidArgumentException('این فهرست قابل صفحه‌بندی نیست.');
        $limit = max(1, min(self::MAX_PAGE, $limit));
        $result = $this->executor->execute($user, $tool, [...$arguments, 'offset' => max(0, $offset), 'limit' => $limit]);
        $presentation = $result['presentation'] ?? null;
        if (($presentation['type'] ?? null) !== 'table') throw new InvalidArgumentException((string) ($result['model']['error'] ?? 'این فهرست دیگر در دسترس نیست.'));
        $rows = $presentation['rows'] ?? [];

        return [
            'rows' => $rows,
            'row_actions' => $presentation['row_actions'] ?? array_fill(0, count($rows), null),
            'total' => (int) ($presentation['count'] ?? 0),
            'next_offset' => $offset + count($rows),
        ];
    }

    /** پیشنهادهای محدودکردن فهرست؛ فقط اگر کاربر بزند به AI می‌رود. */
    private function suggestions(string $tool, array $arguments): array
    {
        $prompts = match ($tool) {
            'list_appointments' => ['فقط نوبت‌های امروز را نشان بده', 'نوبت‌های کنسل‌شدهٔ این ماه را نشان بده', 'نوبت‌های این ماه را به تفکیک پزشک بشمار'],
            'list_followups', 'list_service_followups' => ['فقط پیگیری‌های امروز را نشان بده', 'پیگیری‌های عقب‌افتاده را نشان بده'],
            'query_clinic_records' => ($arguments['dataset'] ?? '') === 'patients'
                ? ['بیمارانی که این ماه پرونده تشکیل داده‌اند', 'بیماران یک پزشک خاص را نشان بده']
                : ['فقط موارد این ماه را نشان بده'],
            'search_people', 'search_patients', 'find_patient_photos' => ['با نام کامل‌تر جست‌وجو کن'],
            'preview_doctor_patients' => ['فقط بیماران این ماه همین پزشک را نشان بده'],
            default => [],
        };

        return array_map(fn (string $prompt) => ['label' => $prompt, 'prompt' => $prompt], $prompts);
    }
}
