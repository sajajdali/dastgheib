<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StaffTaskAttachment extends Model
{
    protected $fillable = [
        'staff_task_id', 'user_id', 'staff_id', 'uploader_name', 'kind', 'path',
        'original_name', 'mime', 'size',
    ];

    protected $appends = ['url', 'uploader_avatar_url'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(StaffTask::class, 'staff_task_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }

    public function getUploaderAvatarUrlAttribute(): ?string
    {
        return $this->staff?->avatar_url;
    }
}
