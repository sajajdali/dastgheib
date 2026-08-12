<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppointmentNoteMessage extends Model
{
    use Auditable;

    public function activitySection(): string { return 'یادداشت نوبت'; }

    protected $fillable = ['appointment_key', 'appointment_id', 'user_id', 'message_type', 'message', 'audio_path', 'audio_duration', 'image_path', 'requires_secretary_attention', 'secretary_seen_at'];

    protected $casts = ['requires_secretary_attention' => 'boolean', 'secretary_seen_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
}
