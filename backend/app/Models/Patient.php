<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Concerns\Auditable;

class Patient extends Model
{
    use HasFactory, Auditable;

    public function activitySection(): string { return 'پرونده‌ها'; }
    public function activityLabel(): string { return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->file_number ? 'پرونده '.$this->file_number : 'پرونده #'.$this->getKey()); }

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'file_number',
        'gender',
        'birth_date',
        'area',
        'city',
        'financial_status',
        'customer_level',
        'patient_history',
        'medical_history',
        'national_id',
        'foreign_national_code',
        'father_name',
        'marital_status',
        'marriage_date',
        'education',
        'second_phone',
        'address',
        'profile_photo_path',
        'profile_thumbnail_path',
        'wallet_balance',
    ];

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function mediaFolders()
    {
        return $this->hasMany(PatientMediaFolder::class);
    }

    public function media()
    {
        return $this->hasMany(PatientMedia::class);
    }

    // یک ویژگی مجازی (Accessor) برای محاسبه آنی موجودی کیف پول بیمار
    public function getWalletBalanceAttribute()
    {
        return $this->walletLedgerSnapshot()['balance'];
    }

    /** Remaining spendable part of a specific wallet credit. */
    public function walletCreditRemaining(int $transactionId): float
    {
        return $this->walletLedgerSnapshot()['credits'][$transactionId]['remaining'] ?? 0;
    }

    /**
     * Replay the immutable wallet ledger in time order. Referral credits are
     * consumed before permanent credits and only while they are valid. A
     * reversal removes the remaining part of its own source credit, never an
     * unrelated manual balance.
     */
    public function walletLedgerSnapshot(): array
    {
        $transactions = $this->walletTransactions()
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'type', 'amount', 'source_type', 'reversed_transaction_id', 'expires_at', 'created_at']);
        $credits = [];
        $allocations = [];

        $expire = static function (array &$lots, $at): void {
            foreach ($lots as &$lot) {
                if ($lot['reward'] && $lot['expires_at'] && $lot['expires_at']->lt($at)) {
                    $lot['remaining'] = 0;
                }
            }
            unset($lot);
        };

        foreach ($transactions as $transaction) {
            $at = $transaction->created_at ?: now();
            $expire($credits, $at);
            $amount = max(0, (float) $transaction->amount);

            if ($transaction->type === 'deposit') {
                $credits[(int) $transaction->id] = [
                    'remaining' => $amount,
                    'reward' => $transaction->source_type === 'referral_reward',
                    'expires_at' => $transaction->expires_at,
                ];
                continue;
            }

            if ($transaction->source_type === 'reversal' && $transaction->reversed_transaction_id) {
                $sourceId = (int) $transaction->reversed_transaction_id;
                if (isset($credits[$sourceId])) {
                    $credits[$sourceId]['remaining'] = max(0, $credits[$sourceId]['remaining'] - $amount);
                }
                continue;
            }

            // Spend rewards with the nearest expiry first, then permanent credits.
            $creditIds = array_keys($credits);
            usort($creditIds, static function (int $left, int $right) use ($credits): int {
                $a = $credits[$left];
                $b = $credits[$right];
                if ($a['reward'] !== $b['reward']) return $a['reward'] ? -1 : 1;
                if ($a['reward']) {
                    $aExpiry = $a['expires_at']?->getTimestamp() ?? PHP_INT_MAX;
                    $bExpiry = $b['expires_at']?->getTimestamp() ?? PHP_INT_MAX;
                    if ($aExpiry !== $bExpiry) return $aExpiry <=> $bExpiry;
                }
                return $left <=> $right;
            });
            foreach ($creditIds as $creditId) {
                if ($amount <= 0) break;
                $used = min($amount, $credits[$creditId]['remaining']);
                $credits[$creditId]['remaining'] -= $used;
                $amount -= $used;
                if ($used > 0) {
                    $allocations[] = [
                        'credit_transaction_id' => $creditId,
                        'debit_transaction_id' => (int) $transaction->id,
                        'amount' => $used,
                        'used_at' => $at,
                    ];
                }
            }
        }

        $expire($credits, now());

        return [
            'balance' => array_sum(array_column($credits, 'remaining')),
            'credits' => $credits,
            'allocations' => $allocations,
        ];
    }

    public function getOutstandingDebtAttribute(): int
    {
        if (! $this->file_number && ! $this->phone) {
            return 0;
        }

        $appointments = Appointment::query()
            ->where(function ($query) {
                if ($this->file_number) {
                    $query->where('file_number', $this->file_number);
                }
                if ($this->phone) {
                    $method = $this->file_number ? 'orWhere' : 'where';
                    $query->{$method}('phone', $this->phone);
                }
            })
            ->get(['debt']);

        return (int) $appointments->sum(function ($appointment) {
            $value = str_replace([',', '٬', ' '], '', (string) ($appointment->debt ?? 0));
            return is_numeric($value) ? (int) $value : 0;
        });
    }

    // اضافه کردن به خروجی‌های JSON به صورت خودکار
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function getProfileThumbnailUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_path
            ? Storage::disk('public')->url($this->profile_thumbnail_path)
            : null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_url ?: $this->profile_photo_url;
    }

    protected $appends = ['wallet_balance', 'outstanding_debt', 'profile_photo_url', 'profile_thumbnail_url', 'avatar_url'];
}
