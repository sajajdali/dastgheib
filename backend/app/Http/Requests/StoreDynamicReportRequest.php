<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreDynamicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'columns' => ['required', 'array', 'min:1', 'max:'.count(config('dynamic_reports.fields', []))],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('dynamic_reports.fields', [])))],
            'filters' => ['sometimes', 'array'],
            'filters.values' => ['sometimes', 'array'],
            'filters.values.name' => ['nullable', 'string', 'max:100'],
            'filters.values.family' => ['nullable', 'string', 'max:100'],
            'filters.values.gender' => ['nullable', 'string', Rule::in(['', 'همه', 'مرد', 'زن', 'male', 'female'])],
            'filters.values.phone' => ['nullable', 'string', 'max:30'],
            'filters.values.fileNo' => ['nullable', 'string', 'max:50'],
            'filters.values.city' => ['nullable', 'string', 'max:100'],
            'filters.values.referrer' => ['nullable', 'string', 'max:100'],
            'filters.values.debt' => ['nullable', 'string', 'max:30'],
            'filters.values.deposit' => ['nullable', 'string', 'max:30'],
            'filters.values.noreturn' => ['nullable', 'string', Rule::in(['', 'بدون محدودیت', '۱ ماه', '۲ ماه', '۳ ماه', '۴ ماه', '۵ ماه', '۶ ماه'])],
            'filters.birthDate' => ['sometimes', 'array'],
            'filters.birthDate.from' => ['nullable', 'array', 'size:3'],
            'filters.birthDate.from.*' => ['required', 'integer'],
            'filters.birthDate.to' => ['nullable', 'array', 'size:3'],
            'filters.birthDate.to.*' => ['required', 'integer'],
            'filters.range' => ['sometimes', 'array'],
            'filters.range.amount' => ['sometimes', 'array'],
            'filters.range.amount.from' => ['nullable', 'string', 'max:30'],
            'filters.range.amount.to' => ['nullable', 'string', 'max:30'],
            'filters.multi' => ['sometimes', 'array'],
            'filters.comparators' => ['sometimes', 'array'],
            'filters.comparators.debt' => ['nullable', Rule::in(['lt','gt','eq'])],
            'filters.comparators.deposit' => ['nullable', Rule::in(['lt','gt','eq'])],
            'filters.multi.custseg' => ['sometimes', 'array', 'max:3'],
            'filters.multi.custseg.*' => ['required', 'string', 'distinct', Rule::in(['آبی', 'نقره‌ای', 'طلایی', 'blue', 'silver', 'gold'])],
            'filters.multi.status' => ['sometimes', 'array', 'max:6'],
            'filters.multi.status.*' => ['required', 'string', 'distinct', Rule::in(config('dynamic_reports.appointment_statuses', []))],
            'filters.multi.source' => ['sometimes', 'array', 'max:20'],
            'filters.multi.source.*' => ['required', 'string', 'distinct', 'max:100'],
            'filters.multi.work' => ['sometimes', 'array', 'max:4'],
            'filters.multi.work.*' => ['required', 'string', 'distinct', Rule::in(['انجام شد', 'انجام نشد', 'ترمیم', 'مشاوره'])],
            'filters.multi.section2' => ['sometimes', 'array', 'max:20'],
            'filters.multi.section2.*' => ['required', 'string', 'distinct', 'max:100'],
            'filters.multi.subsection' => ['sometimes', 'array', 'max:30'],
            'filters.multi.subsection.*' => ['required', 'string', 'distinct', 'max:100'],
            'filters.multi.areas' => ['sometimes', 'array', 'max:30'],
            'filters.multi.areas.*' => ['required', 'string', 'distinct', 'max:100'],
            'filters.multi.extra' => ['sometimes', 'array', 'max:30'],
            'filters.multi.extra.*' => ['required', 'string', 'distinct', 'max:150'],
            'filters.multi.finstatus' => ['sometimes', 'array', 'max:4'],
            'filters.multi.finstatus.*' => ['required', 'string', 'distinct', Rule::in(['ضعیف', 'متوسط', 'خوب', 'عالی'])],
            'filters.multi.payment' => ['sometimes', 'array', 'max:30'],
            'filters.multi.payment.*' => ['required', 'string', 'distinct', 'max:100'],
            'filters.multi.account' => ['sometimes', 'array', 'max:30'],
            'filters.multi.account.*' => ['required', 'string', 'distinct', 'max:100'],
        ];
    }

    public function normalizedFilters(): array
    {
        $data = $this->validated();
        $birthDate = [
            'from' => $this->jalaliDate(data_get($data, 'filters.birthDate.from')),
            'to' => $this->jalaliDate(data_get($data, 'filters.birthDate.to')),
        ];
        if ($birthDate['from'] && $birthDate['to'] && $birthDate['from'] > $birthDate['to']) {
            throw ValidationException::withMessages(['filters.birthDate' => 'ابتدای بازه تاریخ تولد باید قبل از انتهای بازه باشد.']);
        }

        return [
            'values' => [
                'name' => $this->text($data, 'name'),
                'family' => $this->text($data, 'family'),
                'gender' => $this->text($data, 'gender'),
                'phone' => $this->digits($this->text($data, 'phone')),
                'fileNo' => $this->digits($this->text($data, 'fileNo')),
                'city' => $this->text($data, 'city'),
                'referrer' => $this->digits($this->text($data, 'referrer')),
                'debt' => $this->money(data_get($data, 'filters.values.debt')),
                'deposit' => $this->money(data_get($data, 'filters.values.deposit')),
                'noreturnMonths' => $this->noReturnMonths($this->text($data, 'noreturn')),
            ],
            'birthDate' => $birthDate,
            'range' => ['amount' => ['from' => $this->money(data_get($data, 'filters.range.amount.from')), 'to' => $this->money(data_get($data, 'filters.range.amount.to'))]],
            'multi' => [
                'custseg' => collect(data_get($data, 'filters.multi.custseg', []))
                    ->map(fn ($level) => ['آبی' => 'blue', 'نقره‌ای' => 'silver', 'طلایی' => 'gold'][$level] ?? $level)
                    ->unique()->values()->all(),
                'status' => collect(data_get($data, 'filters.multi.status', []))->filter()->unique()->values()->all(),
                'source' => collect(data_get($data, 'filters.multi.source', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'work' => collect(data_get($data, 'filters.multi.work', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'section2' => collect(data_get($data, 'filters.multi.section2', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'subsection' => collect(data_get($data, 'filters.multi.subsection', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'areas' => collect(data_get($data, 'filters.multi.areas', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'extra' => collect(data_get($data, 'filters.multi.extra', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'finstatus' => collect(data_get($data, 'filters.multi.finstatus', []))->filter()->unique()->values()->all(),
                'payment' => collect(data_get($data, 'filters.multi.payment', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
                'account' => collect(data_get($data, 'filters.multi.account', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
            ],
            'comparators' => ['debt' => data_get($data, 'filters.comparators.debt', 'eq'), 'deposit' => data_get($data, 'filters.comparators.deposit', 'eq')],
        ];
    }

    private function text(array $data, string $key): string
    {
        return trim((string) data_get($data, 'filters.values.'.$key, ''));
    }

    private function digits(string $value): string
    {
        return strtr($value, [
            '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
            '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9',
        ]);
    }

    private function jalaliDate(mixed $date): ?string
    {
        if (! is_array($date) || count($date) !== 3) return null;
        [$year, $month, $day] = array_map('intval', array_values($date));
        $isLeap = in_array(($year + 12) % 33, [1, 5, 9, 13, 17, 22, 26, 30], true);
        $maxDay = $month <= 6 ? 31 : ($month <= 11 ? 30 : ($isLeap ? 30 : 29));
        if ($year < 1200 || $year > 1500 || $month < 1 || $month > 12 || $day < 1 || $day > $maxDay) {
            throw ValidationException::withMessages(['filters.birthDate' => 'تاریخ تولد انتخاب‌شده معتبر نیست.']);
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function noReturnMonths(string $value): int
    {
        $normalized = $this->digits($value);
        return preg_match('/^([1-6])\s*ماه$/u', $normalized, $matches) ? (int) $matches[1] : 0;
    }

    private function money(mixed $value): ?string
    {
        $value = $this->digits(trim((string) $value));
        $value = str_replace([',', '٬', ' '], '', $value);
        return $value !== '' && is_numeric($value) ? $value : null;
    }
}
