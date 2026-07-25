<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceMonth extends Model
{
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
