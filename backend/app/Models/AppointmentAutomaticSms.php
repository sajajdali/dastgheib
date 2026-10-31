<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentAutomaticSms extends Model
{
    protected $table = 'appointment_automatic_sms';

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

    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function project(): BelongsTo { return $this->belongsTo(AutomaticSmsProject::class, 'project_id'); }
    public function scenario(): BelongsTo { return $this->belongsTo(AutomaticSmsScenario::class, 'scenario_id'); }
    public function step(): BelongsTo { return $this->belongsTo(AutomaticSmsScenarioStep::class, 'scenario_step_id'); }
}
