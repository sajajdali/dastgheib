<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_number',
        'date',
        'service',
        'status',
        'doctor',
        'referral_code',
        'club_score',
        'amount'
    ];
}
