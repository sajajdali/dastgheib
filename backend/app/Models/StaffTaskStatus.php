<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffTaskStatus extends Model
{
    protected $fillable = [
        'title', 'color', 'effect', 'amount', 'sort_order', 'is_system',
    ];

    protected $casts = [
        'color' => 'integer',
        'amount' => 'integer',
        'sort_order' => 'integer',
        'is_system' => 'boolean',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(StaffTask::class, 'status_id');
    }

    public function signedAmount(): int
    {
        if ($this->effect === 'plus') return (int) $this->amount;
        if ($this->effect === 'minus') return -((int) $this->amount);
        return 0;
    }
}
