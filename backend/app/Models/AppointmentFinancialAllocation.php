<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentFinancialAllocation extends Model
{
    protected $guarded = [];
    protected $casts = ['is_addon' => 'boolean', 'quantity' => 'decimal:3', 'gross_amount' => 'integer', 'discount_amount' => 'integer', 'allocated_amount' => 'integer'];
    public function transaction() { return $this->belongsTo(AppointmentFinancialTransaction::class, 'transaction_id'); }
}
