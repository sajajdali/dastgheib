<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionInvitation extends Model
{
    protected $guarded = [];
    protected $casts = [
        'form_snapshot' => 'array',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'answered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
