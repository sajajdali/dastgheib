<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReportWidgetSnapshot extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'filters' => 'array',
        'result' => 'array',
        'progress' => 'integer',
        'completed_at' => 'datetime',
    ];
}
