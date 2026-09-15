<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynamicReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'progress' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(DynamicReportRow::class, 'report_id');
    }
}
