<?php

namespace App\Reporting\DynamicReports;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * شرط‌های مشترک فیلترهای نوبت در هر دو مبنای گزارش (نوبت‌دهی و پرونده‌ها)،
 * تا یک فیلتر در هر دو جا یک معنی داشته باشد.
 */
final class AppointmentReportMatch
{
    /**
     * بخش یا زیر بخش: section_id نوبت‌های جدید، بخش کالای انبار، یا نام ذخیره‌شده در نوبت‌های قدیمی
     * (در داده‌های قدیمی «subsection» گروه اصلی و «section» زیر بخش است).
     */
    public static function sections(Builder|QueryBuilder $query, string $alias, array $names, string $legacyKey): void
    {
        $names = array_values(array_filter(array_map(fn ($name) => trim((string) $name), $names)));
        if ($names === []) return;
        $ids = app(ReportServiceSections::class)->idsForNames($names);
        // خدمتی که section_id ندارد با بخش همان کالا در انبار سنجیده می‌شود (مثل ستون «بخش»).
        $services = $ids === [] ? [] : Inventory::query()->whereIn('section_id', $ids)->pluck('name')->all();
        $query->where(function ($match) use ($alias, $names, $ids, $legacyKey, $services) {
            foreach ($services as $service) {
                $match->orWhereRaw("JSON_SEARCH({$alias}.services, 'one', ?, NULL, '$[*].name') IS NOT NULL", [$service]);
            }
            foreach ($ids as $id) {
                $match->orWhereRaw("JSON_SEARCH({$alias}.services, 'one', ?, NULL, '$[*].section_id') IS NOT NULL", [$id])
                    ->orWhereRaw("JSON_CONTAINS({$alias}.services, JSON_OBJECT('section_id', CAST(? AS UNSIGNED)))", [$id]);
            }
            foreach ($names as $name) {
                $match->orWhereRaw("JSON_SEARCH({$alias}.services, 'one', ?, NULL, '$[*].{$legacyKey}') IS NOT NULL", [$name]);
            }
        });
    }

    /** پزشک یا مشاور: ستون نوبت (که ممکن است چند نام با «،» داشته باشد) یا نام روی هر خدمت. */
    public static function person(Builder|QueryBuilder $query, string $alias, string $column, array $names): void
    {
        $names = array_values(array_filter(array_map(fn ($name) => trim((string) $name), $names)));
        if ($names === []) return;
        // چند نام در یک ستون و نام روی خدمت‌ها با توابع MySQL سنجیده می‌شود (تست‌های SQLite فقط ستون را دارند).
        $mysql = $query->getConnection()->getDriverName() === 'mysql';
        $query->where(function ($match) use ($alias, $column, $names, $mysql) {
            foreach ($names as $name) {
                $match->orWhere("{$alias}.{$column}", $name);
                if (! $mysql) continue;
                $match->orWhereRaw("CONCAT('،', REPLACE(REPLACE({$alias}.{$column}, '، ', '،'), ',', '،'), '،') LIKE ?", ['%،'.addcslashes($name, '%_\\').'،%'])
                    ->orWhereRaw("JSON_SEARCH({$alias}.services, 'one', ?, NULL, '$[*].{$column}') IS NOT NULL", [$name]);
            }
        });
    }

    /** روش پرداخت یا حساب: اولین پرداخت ذخیره‌شده روی نوبت یا هر پرداخت فعال در دفتر مالی آن. */
    public static function payment(Builder|QueryBuilder $query, string $alias, string $column, array $values): void
    {
        $values = array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values)));
        if ($values === []) return;
        $query->where(fn ($match) => $match->whereIn("{$alias}.{$column}", $values)
            ->orWhereExists(fn (QueryBuilder $ledger) => $ledger->selectRaw('1')
                ->from('appointment_financial_transactions as payment_ledger')
                ->whereColumn('payment_ledger.appointment_id', "{$alias}.id")
                ->whereIn('payment_ledger.type', ['payment', 'debt_settlement'])
                ->whereNull('payment_ledger.voided_at')
                ->whereIn("payment_ledger.{$column}", $values)));
    }

    /** انجام کار؛ «انجام نشد» یعنی نوبتی که هنوز نتیجه‌ای برایش ثبت نشده است. */
    public static function work(Builder|QueryBuilder $query, string $alias, array $values): void
    {
        $values = array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values)));
        if ($values === []) return;
        $query->where(function ($match) use ($alias, $values) {
            $stored = array_values(array_diff($values, ['انجام نشد']));
            if ($stored !== []) $match->orWhereIn("{$alias}.done", $stored);
            if (in_array('انجام نشد', $values, true)) {
                $match->orWhere(fn ($pending) => $pending->whereNull("{$alias}.done")->orWhereIn("{$alias}.done", ['', 'انجام نشد']));
            }
        });
    }

    /** مقایسهٔ مبلغ یک ستون یا عبارت پولی با مقدار وارد شده (کمتر، بیشتر یا برابر). */
    public static function compare(Builder|QueryBuilder $query, string $expression, ?string $operator, mixed $value): void
    {
        if ($value === null || $value === '' || ! is_numeric($value)) return;
        $operator = ['lt' => '<', 'gt' => '>', 'eq' => '='][$operator ?? 'eq'] ?? '=';
        // CAST: مقدار bind‌شده در SQLite متن است و با عدد مقایسه نمی‌شود؛ در MySQL اثری ندارد.
        $query->whereRaw("{$expression} {$operator} CAST(? AS DECIMAL(18,2))", [(float) $value]);
    }

    public static function money(string $column): string
    {
        return "CAST(REPLACE(REPLACE(REPLACE(COALESCE({$column}, '0'), ',', ''), '٬', ''), ' ', '') AS DECIMAL(18,2))";
    }

    /** بیعانهٔ ثبت‌شده در دفتر مالی نوبت. */
    public static function deposit(string $alias): string
    {
        return "(SELECT COALESCE(SUM(deposit_ledger.amount),0) FROM appointment_financial_transactions AS deposit_ledger WHERE deposit_ledger.appointment_id = {$alias}.id AND deposit_ledger.type = 'deposit' AND deposit_ledger.voided_at IS NULL)";
    }
}
