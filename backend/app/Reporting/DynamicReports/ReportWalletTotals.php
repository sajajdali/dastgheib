<?php

namespace App\Reporting\DynamicReports;

use Illuminate\Support\Facades\DB;

/**
 * جمع واریز یا برداشت کیف پول بیمار در بازهٔ گزارش، از دفتر wallet_transactions.
 *
 * - واریز: بیعانه، برگشت پرداخت نوبت حذف‌شده، پاداش معرف و واریز دستی.
 * - برداشت: پرداخت نوبت از کیف پول و برداشت دستی.
 * - ردیف‌های «برگشت» (source_type = reversal) خودشان حساب نمی‌شوند؛ مبلغ برگشت‌خورده از
 *   همان ردیف اصلی کم می‌شود، تا برگشتِ بخشی از یک واریز هم درست حساب شود.
 * - تاریخ هر ردیف همان created_at است (به وقت تهران) و بازهٔ شمسی گزارش به میلادی تبدیل می‌شود.
 */
final class ReportWalletTotals
{
    public const TYPES = ['walletDeposit' => 'deposit', 'walletWithdraw' => 'withdraw'];

    /**
     * عبارت SQL جمع برای بیماری که شناسه‌اش {$patientId} است.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function expression(string $patientId, string $type, array $filters): array
    {
        if (! in_array($type, self::TYPES, true)) throw new \InvalidArgumentException("Unknown wallet transaction type [{$type}].");
        $sql = "(SELECT COALESCE(SUM(CASE WHEN wallet_ledger.amount - COALESCE(wallet_reversal.amount, 0) > 0"
            ." THEN wallet_ledger.amount - COALESCE(wallet_reversal.amount, 0) ELSE 0 END), 0)"
            ." FROM wallet_transactions AS wallet_ledger"
            ." LEFT JOIN wallet_transactions AS wallet_reversal ON wallet_reversal.reversed_transaction_id = wallet_ledger.id"
            ." AND wallet_reversal.source_type = 'reversal'"
            ." WHERE wallet_ledger.patient_id = {$patientId} AND wallet_ledger.type = ?"
            ." AND (wallet_ledger.source_type IS NULL OR wallet_ledger.source_type <> 'reversal')";
        $bindings = [$type];
        [$from, $to] = self::range($filters);
        if ($from) { $sql .= ' AND wallet_ledger.created_at >= ?'; $bindings[] = $from; }
        if ($to) { $sql .= ' AND wallet_ledger.created_at <= ?'; $bindings[] = $to; }

        return [$sql.')', $bindings];
    }

    public static function forPatient(?int $patientId, string $type, array $filters): ?float
    {
        if (! $patientId) return null;
        [$sql, $bindings] = self::expression('?', $type, $filters);
        $row = DB::selectOne('SELECT '.$sql.' AS total', [$patientId, ...$bindings]);
        return round((float) ($row->total ?? 0));
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function range(array $filters): array
    {
        $dates = app(JalaliMonthWindow::class);
        $from = data_get($filters, 'reportDate.from');
        $to = data_get($filters, 'reportDate.to');
        return [
            $from ? $dates->toGregorian($from).' 00:00:00' : null,
            $to ? $dates->toGregorian($to).' 23:59:59' : null,
        ];
    }
}
