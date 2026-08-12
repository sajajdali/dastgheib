<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Service extends Model
{
    use HasFactory, Auditable;

    public function activitySection(): string { return 'خدمات'; }

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
