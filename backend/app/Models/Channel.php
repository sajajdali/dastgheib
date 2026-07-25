<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    // تعیین اینکه ستون 'name' قابلیت پر شدن گروهی (mass assignment) را داشته باشد
    protected $fillable = [
        'name',
        'icon',
    ];
}
