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
        $transactions = $this->walletTransactions()->get(['type', 'amount', 'source_type', 'expires_at']);
        $withdrawals = (float) $transactions->where('type', 'withdraw')->sum('amount');
        $permanent = (float) $transactions->where('type', 'deposit')->where('source_type', '!=', 'referral_reward')->sum('amount');
        $rewards = $transactions->where('type', 'deposit')->where('source_type', 'referral_reward');
        $expiredRewards = (float) $rewards->filter(fn ($transaction) => $transaction->expires_at && $transaction->expires_at->isPast())->sum('amount');
        $activeRewards = (float) $rewards->reject(fn ($transaction) => $transaction->expires_at && $transaction->expires_at->isPast())->sum('amount');

        // مصرف اعتبار از پاداش‌هایی که زودتر منقضی می‌شوند آغاز می‌شود؛ در نتیجه
        // انقضا هرگز شارژ دستی یا بیعانهٔ باقی‌مانده را از بین نمی‌برد.
        $activeRewardRemaining = max(0, $activeRewards - max(0, $withdrawals - $expiredRewards));
        $permanentRemaining = max(0, $permanent - max(0, $withdrawals - $expiredRewards - $activeRewards));

        return $activeRewardRemaining + $permanentRemaining;
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
