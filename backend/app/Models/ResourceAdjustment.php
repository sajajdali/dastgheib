<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ResourceAdjustment extends Model
{
    use Auditable;

    protected $fillable = [
        'month', 'resource_type', 'resource_id', 'amount', 'reason', 'created_by',
        'kind', 'occurred_on', 'title', 'description', 'status', 'created_by_name',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function activitySection(): string { return 'حقوق و تسویه'; }
}
