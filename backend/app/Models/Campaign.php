<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = ['name', 'channel_id', 'starts_on', 'ends_on', 'budget', 'note', 'active', 'ui_payload'];

    protected $casts = [
        'budget' => 'decimal:2',
        'active' => 'boolean',
        'ui_payload' => 'array',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }
}
