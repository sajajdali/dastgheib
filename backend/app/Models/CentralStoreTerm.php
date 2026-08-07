<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentralStoreTerm extends Model
{
    protected $fillable = [
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(CentralStoreTermAcceptance::class);
    }
}
