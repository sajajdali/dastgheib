<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SalaryAdvance extends Model
{
    use Auditable;

    protected $fillable = [
        'resource_type', 'resource_id', 'user_id', 'requested_amount', 'approved_amount',
        'reason', 'manager_note', 'status', 'deduct_from_salary', 'installment_count',
        'deduction_start_month', 'reviewed_by', 'reviewed_at', 'paid_at', 'cancelled_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2', 'approved_amount' => 'decimal:2',
        'deduct_from_salary' => 'boolean', 'installment_count' => 'integer',
        'reviewed_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function installments() { return $this->hasMany(SalaryAdvanceInstallment::class); }
    public function activitySection(): string { return 'درخواست‌های مساعده'; }
}
