<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\WalletAllocationService;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use Auditable;

    public function activitySection(): string { return 'کیف پول'; }

    protected $fillable = [
        'patient_id', 'type', 'amount', 'description', 'source_type', 'source_key',
        'appointment_id', 'reversed_transaction_id', 'created_by', 'metadata', 'reversed_at', 'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'reversed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referralRewardLines()
    {
        return $this->hasMany(ReferralRewardLine::class);
    }

    public function creditAllocations()
    {
        return $this->hasMany(WalletTransactionAllocation::class, 'credit_transaction_id');
    }

    protected static function booted(): void
    {
        $sync = static function (WalletTransaction $transaction): void {
            if ($transaction->patient_id) app(WalletAllocationService::class)->sync(Patient::findOrFail($transaction->patient_id));
        };
        static::saved($sync);
        static::deleted($sync);
    }
}
