<?php

namespace App\Services\Assistant\Billing;

use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * کیف پول دلاری هر tenant در دیتابیس مرکزی.
 * موجودی هرگز منفی نمی‌شود: پیش از هر فراخوانی API سقف هزینهٔ آن رزرو می‌شود
 * و پس از پاسخ، هزینهٔ واقعی (که از رزرو بیشتر نیست) کسر و رزرو آزاد می‌شود.
 */
final class AiWallet
{
    private const HOLD_MINUTES = 10;

    public function __construct(private readonly AiBillingSettings $settings) {}

    public function state(string $tenantId): array
    {
        $settings = $this->settings->current();
        $wallet = $this->wallet($tenantId);
        $held = $this->heldMicros($tenantId);
        $balance = (int) $wallet->balance_micros;

        return [
            'balance_micros' => $balance,
            'held_micros' => $held,
            'available_micros' => max(0, $balance - $held),
            'min_balance_micros' => (int) $settings->min_balance_micros,
            'low_balance_micros' => (int) $settings->low_balance_micros,
            'blocked' => $balance < (int) $settings->min_balance_micros,
            'low' => $balance < (int) $settings->low_balance_micros,
        ];
    }

    public function ensureUsable(string $tenantId): void
    {
        $state = $this->state($tenantId);
        if ($state['blocked']) throw new AiBalanceException('below_minimum', $state['balance_micros'], $state['min_balance_micros']);
    }

    public function reserve(string $tenantId, int $amount): int
    {
        return $this->central()->transaction(function () use ($tenantId, $amount): int {
            $wallet = $this->lock($tenantId);
            $available = (int) $wallet->balance_micros - $this->heldMicros($tenantId);
            if ($available < $amount) {
                throw new AiBalanceException('insufficient_for_request', (int) $wallet->balance_micros, (int) $this->settings->current()->min_balance_micros);
            }

            return (int) $this->central()->table('central_ai_holds')->insertGetId([
                'tenant_id' => $tenantId,
                'amount_micros' => $amount,
                'expires_at' => now()->addMinutes(self::HOLD_MINUTES),
                'created_at' => now(),
            ]);
        });
    }

    public function release(int $holdId): void
    {
        $this->central()->table('central_ai_holds')->where('id', $holdId)->delete();
    }

    /** ردیف مصرف را ثبت، هزینه را کسر و رزرو را آزاد می‌کند. */
    public function settle(int $holdId, array $usage): int
    {
        $tenantId = $usage['tenant_id'];

        return $this->central()->transaction(function () use ($holdId, $usage, $tenantId): int {
            $wallet = $this->lock($tenantId);
            $this->central()->table('central_ai_holds')->where('id', $holdId)->delete();
            $available = max(0, (int) $wallet->balance_micros - $this->heldMicros($tenantId));
            $charge = (int) $usage['charge_micros'];
            $collect = min($charge, $available);
            $balanceAfter = (int) $wallet->balance_micros - $collect;
            $now = now();
            $tehran = now('Asia/Tehran');
            $jalali = new JalaliMonthWindow;
            $date = $jalali->date($tehran);

            $usageId = (int) $this->central()->table('central_ai_usage')->insertGetId([
                ...$usage,
                'uncollected_micros' => $charge - $collect,
                'jalali_date' => $date,
                'jalali_week' => $jalali->date($tehran->copy()->subDays(($tehran->dayOfWeek + 1) % 7)),
                'jalali_month' => substr($date, 0, 7),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)
                ->update(['balance_micros' => $balanceAfter, 'updated_at' => $now]);
            if ($collect > 0) {
                $this->central()->table('central_ai_wallet_transactions')->insert([
                    'tenant_id' => $tenantId, 'type' => 'usage', 'amount_micros' => -$collect,
                    'balance_after_micros' => $balanceAfter, 'usage_id' => $usageId, 'created_at' => $now,
                ]);
            }

            return $usageId;
        });
    }

    public function charge(string $tenantId, int $amount, ?int $adminId, ?int $paidToman, ?string $reference, ?string $note): array
    {
        if ($amount <= 0) throw new InvalidArgumentException('مبلغ شارژ باید بیشتر از صفر باشد.');

        return $this->apply($tenantId, 'charge', $amount, $adminId, $paidToman, $reference, $note);
    }

    /** اصلاح دستی؛ مقدار منفی فقط تا جایی که موجودی آزاد اجازه دهد. */
    public function adjust(string $tenantId, int $amount, ?int $adminId, string $note): array
    {
        if ($amount === 0) throw new InvalidArgumentException('مبلغ اصلاح نمی‌تواند صفر باشد.');

        return $this->apply($tenantId, 'adjustment', $amount, $adminId, null, null, $note);
    }

    private function apply(string $tenantId, string $type, int $amount, ?int $adminId, ?int $paidToman, ?string $reference, ?string $note): array
    {
        return $this->central()->transaction(function () use ($tenantId, $type, $amount, $adminId, $paidToman, $reference, $note): array {
            $wallet = $this->lock($tenantId);
            $available = (int) $wallet->balance_micros - $this->heldMicros($tenantId);
            if ($amount < 0 && $available + $amount < 0) {
                throw new InvalidArgumentException('کسر این مبلغ موجودی را منفی می‌کند.');
            }
            $balanceAfter = (int) $wallet->balance_micros + $amount;
            $now = now();
            $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)
                ->update(['balance_micros' => $balanceAfter, 'updated_at' => $now]);
            $id = $this->central()->table('central_ai_wallet_transactions')->insertGetId([
                'tenant_id' => $tenantId, 'type' => $type, 'amount_micros' => $amount,
                'balance_after_micros' => $balanceAfter, 'central_admin_id' => $adminId,
                'paid_toman' => $paidToman, 'reference' => $reference, 'note' => $note, 'created_at' => $now,
            ]);

            return ['id' => $id, 'balance_micros' => $balanceAfter];
        });
    }

    private function wallet(string $tenantId): object
    {
        $row = $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)->first();
        if ($row) return $row;
        $this->central()->table('central_ai_wallets')->insertOrIgnore([
            'tenant_id' => $tenantId, 'balance_micros' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)->first();
    }

    /** نوشتن روی ردیف کیف پول، قفل ردیف را در MySQL و قفل نوشتن را در SQLite می‌گیرد. */
    private function lock(string $tenantId): object
    {
        $this->wallet($tenantId);
        $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)->update(['updated_at' => now()]);

        return $this->central()->table('central_ai_wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
    }

    private function heldMicros(string $tenantId): int
    {
        return (int) $this->central()->table('central_ai_holds')
            ->where('tenant_id', $tenantId)->where('expires_at', '>', now())->sum('amount_micros');
    }

    private function central()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
