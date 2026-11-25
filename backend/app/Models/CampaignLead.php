<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLead extends Model
{
    protected $fillable = [
        'campaign_id',
        'local_id',
        'sort_order',
        'full_name',
        'phone',
        'gender',
        'consultant',
        'source',
        'status',
        'interest',
        'reason',
        'contact_date',
        'contact_time',
        'follow_up_date',
        'subsection_id',
        'subsection_name',
        'appointment_registered',
        'appointment_done',
        'meaningful',
        'payload',
    ];

    protected $casts = [
        'appointment_registered' => 'boolean',
        'appointment_done' => 'boolean',
        'meaningful' => 'boolean',
        'payload' => 'array',
        'subsection_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
