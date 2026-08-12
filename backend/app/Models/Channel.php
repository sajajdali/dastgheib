<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use Auditable;

    public function activitySection(): string { return 'کانال‌ها'; }

    // تعیین اینکه ستون 'name' قابلیت پر شدن گروهی (mass assignment) را داشته باشد
    protected $fillable = [
        'name',
        'icon',
    ];
}
