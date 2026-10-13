<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentNoteChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $appointmentKey,
        public readonly int $messageCount,
        public readonly bool $hasUnreadDoctorNote,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("clinic.{$this->tenantId}.appointments")];
    }

    public function broadcastAs(): string
    {
        return 'appointment.note.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_key' => $this->appointmentKey,
            'note_message_count' => $this->messageCount,
            'doctor_note_unread' => $this->hasUnreadDoctorNote,
        ];
    }
}
