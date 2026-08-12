<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AttendanceMonth extends Model
{
    use Auditable;

    public function activitySection(): string { return 'حضور و غیاب'; }

    protected $fillable = [
        'resource_type',
        'resource_id',
        'year',
        'month',
        'name',
        'daily_hours',
        'days',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'daily_hours' => 'decimal:2',
        'days' => 'array',
    ];
}
