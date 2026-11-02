<?php

namespace App\Services\Assistant\Billing;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AiUsageFilters
{
    /** فیلترهای مشترک گزارش؛ تاریخ‌ها شمسی (با ارقام فارسی یا انگلیسی، با / یا -). */
    public static function fromRequest(Request $request, bool $central = false): array
    {
        $request->merge([
            'from' => self::normalizeDate($request->input('from')),
            'to' => self::normalizeDate($request->input('to')),
            'month' => self::normalizeMonth($request->input('month')),
        ]);
        $validated = $request->validate([
            'from' => ['nullable', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'to' => ['nullable', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'month' => ['nullable', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/'],
            'user_ids' => ['nullable', 'array', 'max:200'],
            'user_ids.*' => ['integer', 'min:1'],
            'models' => ['nullable', 'array', 'max:50'],
            'models.*' => ['string', 'max:80'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::in(array_keys(AiUsageReport::TYPES))],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [Rule::in(array_keys(AiUsageReport::STATUSES))],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'request_group' => ['nullable', 'uuid'],
            'tenant_id' => $central ? ['nullable', 'string', 'max:255'] : ['prohibited'],
        ], [
            'from.regex' => 'تاریخ شروع باید شمسی و معتبر باشد.',
            'to.regex' => 'تاریخ پایان باید شمسی و معتبر باشد.',
        ]);
        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['from'] > $validated['to']) {
            throw ValidationException::withMessages(['from' => 'تاریخ شروع نباید بعد از تاریخ پایان باشد.']);
        }

        return array_filter($validated, fn ($value) => $value !== null && $value !== []);
    }

    public static function grouping(Request $request, bool $central = false): array
    {
        $groups = array_keys(AiUsageReport::GROUPS);
        if (! $central) $groups = array_values(array_diff($groups, ['tenant']));
        $validated = $request->validate([
            'group_by' => ['nullable', Rule::in($groups)],
            'then_by' => ['nullable', Rule::in($groups)],
        ]);

        return [$validated['group_by'] ?? 'day', $validated['then_by'] ?? null];
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = self::digits($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);

        return $value;
    }

    private static function normalizeMonth(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = self::digits($value);
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m)) return sprintf('%04d-%02d', $m[1], $m[2]);

        return $value;
    }

    private static function digits(mixed $value): string
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '/' => '-',
        ]);
    }
}
