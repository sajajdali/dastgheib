<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use Auditable;

    public function activitySection(): string { return 'پیگیری'; }

    protected $fillable = [
        'full_name',
        'phone',
        'date',
        'follow_up_date',
        'gender',
        'consultant',
        'source',
        'status',
        'description',
        'interest'
    ];

    public $timestamps = false;
}
