<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use Auditable;

    public function activitySection(): string { return 'تیکت‌ها'; }

    protected $fillable = ['subject', 'description', 'date', 'owner', 'priority', 'status'];
}
