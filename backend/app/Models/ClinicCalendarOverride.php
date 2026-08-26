<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicCalendarOverride extends Model
{
    protected $fillable = ['jalali_date', 'is_closed', 'title', 'updated_by'];

    protected $casts = ['is_closed' => 'boolean'];
}
