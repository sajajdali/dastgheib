<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StaffTask extends Model
{
    protected $fillable = [
        'title', 'note', 'task_date', 'priority', 'progress', 'status_id', 'auto_expired',
        'expiry_enabled', 'expiry_date', 'expiry_time', 'expiry_status_id', 'image_path',
        'created_by', 'created_by_name', 'registered_at',
    ];

    protected $casts = [
        'task_date' => 'date',
        'expiry_date' => 'date',
        'auto_expired' => 'boolean',
        'expiry_enabled' => 'boolean',
        'progress' => 'integer',
        'registered_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(StaffTaskStatus::class, 'status_id');
    }

    public function expiryStatus(): BelongsTo
    {
        return $this->belongsTo(StaffTaskStatus::class, 'expiry_status_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_task_assignees', 'staff_task_id', 'staff_id')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StaffTaskAttachment::class)->orderBy('id');
    }

    public function voices(): HasMany
    {
        return $this->hasMany(StaffTaskVoice::class)->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
