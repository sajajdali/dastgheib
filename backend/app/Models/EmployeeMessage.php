<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeMessage extends Model
{
    protected $fillable = [
        'resource_type', 'resource_id', 'sender_user_id', 'sender_name', 'body', 'image_path',
        'voice_path', 'voice_duration', 'read_by_employee_at', 'read_by_manager_at',
    ];

    protected $casts = [
        'voice_duration' => 'integer', 'read_by_employee_at' => 'datetime',
        'read_by_manager_at' => 'datetime',
    ];

    protected $appends = ['image_url', 'voice_url'];

    public function getImageUrlAttribute(): ?string { return $this->image_path ? Storage::disk('public')->url($this->image_path) : null; }
    public function getVoiceUrlAttribute(): ?string { return $this->voice_path ? Storage::disk('public')->url($this->voice_path) : null; }
}
