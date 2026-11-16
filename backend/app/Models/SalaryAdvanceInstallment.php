<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdvanceInstallment extends Model
{
    protected $fillable = ['salary_advance_id', 'month', 'amount', 'status', 'deducted_at'];
    protected $casts = ['amount' => 'decimal:2', 'deducted_at' => 'datetime'];
    public function advance() { return $this->belongsTo(SalaryAdvance::class, 'salary_advance_id'); }
}
