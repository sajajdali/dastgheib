<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک مرحلهٔ پیامک اتوماتیک که از پیگیری برای یک مخاطب فعال شده است. */
class FollowupAutomaticSms extends Model
{
    protected $table = 'followup_automatic_sms';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'days_after' => 'integer',
        'attempts' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'disabled_at' => 'datetime',
        'provider_response' => 'array',
    ];

    public function project(): BelongsTo { return $this->belongsTo(AutomaticSmsProject::class, 'project_id'); }
    public function scenario(): BelongsTo { return $this->belongsTo(AutomaticSmsScenario::class, 'scenario_id'); }
}
