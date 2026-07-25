<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentBalanceAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'old_debt' => 'integer',
        'new_debt' => 'integer',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
