<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Patient extends Model
{
    use HasFactory;

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
        'father_name',
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
        $deposits = $this->walletTransactions()->where('type', 'deposit')->sum('amount');
        $withdraws = $this->walletTransactions()->where('type', 'withdraw')->sum('amount');

        return $deposits - $withdraws;
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
