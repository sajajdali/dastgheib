<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $action,
        public readonly int $appointmentId,
        public readonly string $month,
        public readonly int $lockVersion,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("clinic.{$this->tenantId}.appointments")];
    }

    public function broadcastAs(): string
    {
        return 'appointment.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'appointment_id' => $this->appointmentId,
            'month' => $this->month,
            'lock_version' => $this->lockVersion,
        ];
    }

    public static function fromAppointment(Appointment $appointment, string $action = 'updated'): self
    {
        return new self(
            (string) tenant('id'),
            $action,
            (int) $appointment->getKey(),
            (string) $appointment->month,
            (int) $appointment->lock_version,
        );
    }
}
