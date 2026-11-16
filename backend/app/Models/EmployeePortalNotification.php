<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePortalNotification extends Model
{
    protected $fillable = [
        'user_id', 'resource_type', 'resource_id', 'type', 'title', 'body', 'action_tab', 'data', 'read_at',
    ];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
