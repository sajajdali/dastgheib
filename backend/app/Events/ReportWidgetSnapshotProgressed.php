<?php

namespace App\Events;

use App\Models\ReportWidgetSnapshot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportWidgetSnapshotProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly array $snapshot,
    ) {}

    public static function fromSnapshot(ReportWidgetSnapshot $snapshot): self
    {
        return new self((string) tenant('id'), [
            'id' => (string) $snapshot->id,
            'report_key' => (string) $snapshot->report_key,
            'filters' => $snapshot->filters,
            'result' => $snapshot->result,
            'status' => (string) $snapshot->status,
            'progress' => (int) $snapshot->progress,
            'stage' => (string) ($snapshot->stage ?: ''),
            'error' => $snapshot->error,
            'completed_at' => $snapshot->completed_at?->toIso8601String(),
        ]);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("clinic.{$this->tenantId}.report-widgets.{$this->snapshot['id']}")];
    }

    public function broadcastAs(): string
    {
        return 'report-widget.progressed';
    }

    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
