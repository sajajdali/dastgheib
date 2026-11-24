<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTaskSetting extends Model
{
    protected $fillable = ['expiry_default_status_id'];

    public function expiryDefaultStatus(): BelongsTo
    {
        return $this->belongsTo(StaffTaskStatus::class, 'expiry_default_status_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
