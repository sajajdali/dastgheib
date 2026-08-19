<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Channel extends Model
{
    use Auditable;

    public function activitySection(): string { return 'کانال‌ها'; }

    // تعیین اینکه ستون 'name' قابلیت پر شدن گروهی (mass assignment) را داشته باشد
    protected $fillable = [
        'name',
        'icon',
        'icon_image_path',
    ];

    protected $appends = ['icon_image_url'];

    public function getIconImageUrlAttribute(): ?string
    {
        return $this->icon_image_path
            ? Storage::disk('public')->url($this->icon_image_path)
            : null;
    }
}
