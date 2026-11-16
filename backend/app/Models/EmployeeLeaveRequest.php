<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveRequest extends Model
{
    use Auditable;

    protected $fillable = [
        'resource_type', 'resource_id', 'user_id', 'leave_type', 'start_date', 'end_date',
        'start_time', 'end_time', 'reason', 'status', 'manager_note', 'reviewed_by',
        'reviewed_at', 'cancelled_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function activitySection(): string { return 'درخواست‌های مرخصی'; }
}
