<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomaticSmsScenarioStep extends Model
{
    protected $fillable = ['scenario_id', 'sort_order', 'days_after', 'send_at', 'sms_template'];
    protected $casts = ['days_after' => 'integer', 'sort_order' => 'integer'];
}
