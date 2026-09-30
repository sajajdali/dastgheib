<?php

namespace App\Jobs;

use App\Events\ReportWidgetSnapshotProgressed;
use App\Models\ReportWidgetSnapshot;
use App\Services\ClinicReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateCustomerSegmentsWidget implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 2;
    public bool $failOnTimeout = true;

    public function __construct(public string $snapshotId)
    {
        $this->onConnection('database-central');
        $this->onQueue('reports');
    }

    public function handle(ClinicReportService $reports): void
    {
        $snapshot = ReportWidgetSnapshot::query()->findOrFail($this->snapshotId);
        $this->updateSnapshot($snapshot, [
            'status' => 'processing', 'progress' => 1,
            'stage' => 'شروع محاسبه دسته‌بندی مشتریان', 'error' => null,
        ]);

        $result = $reports->customerSegmentsReport(
            $snapshot->filters ?? [],
            function (int $progress, string $stage) use ($snapshot): void {
                $this->updateSnapshot($snapshot, ['progress' => $progress, 'stage' => $stage]);
            }
        );

        $this->updateSnapshot($snapshot, [
            'result' => $result,
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'گزارش آماده است',
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $snapshot = ReportWidgetSnapshot::query()->find($this->snapshotId);
        if (! $snapshot) return;
        $this->updateSnapshot($snapshot, [
            'status' => 'failed',
            'stage' => 'محاسبه گزارش ناموفق بود',
            'error' => mb_substr($exception?->getMessage() ?: 'خطای ناشناخته', 0, 2000),
        ]);
    }

    private function updateSnapshot(ReportWidgetSnapshot $snapshot, array $attributes): void
    {
        $snapshot->update($attributes);
        $snapshot->refresh();

        try {
            event(ReportWidgetSnapshotProgressed::fromSnapshot($snapshot));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
