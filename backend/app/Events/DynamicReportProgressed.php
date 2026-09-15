<?php

namespace App\Events;

use App\Models\DynamicReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DynamicReportProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $reportId,
        public readonly string $status,
        public readonly int $progress,
        public readonly string $stage,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly ?string $error = null,
    ) {}

    public static function fromReport(DynamicReport $report): self
    {
        return new self(
            (string) tenant('id'),
            (string) $report->id,
            (string) $report->status,
            (int) $report->progress,
            (string) ($report->stage ?: ''),
            (int) $report->total_rows,
            (int) $report->processed_rows,
            $report->error,
        );
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("clinic.{$this->tenantId}.reports.{$this->reportId}")];
    }

    public function broadcastAs(): string
    {
        return 'report.progressed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->reportId, 'status' => $this->status, 'progress' => $this->progress,
            'stage' => $this->stage, 'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows, 'error' => $this->error,
        ];
    }
}
