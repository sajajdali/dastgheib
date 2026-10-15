<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSaveVersion;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use Auditable, HasSaveVersion;

    protected $appends = ['save_version'];

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
        'interest',
        'campaign_id',
    ];

    public $timestamps = false;
}
