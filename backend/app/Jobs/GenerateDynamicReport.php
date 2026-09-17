<?php

namespace App\Jobs;

use App\Events\DynamicReportProgressed;
use App\Models\DynamicReport;
use App\Models\DynamicReportRow;
use App\Reporting\DynamicReports\DynamicReportChunkContextLoader;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\DynamicReportFilterPipeline;
use App\Reporting\DynamicReports\DynamicReportRowBuilder;
use App\Reporting\DynamicReports\Filters\CustomerLevelFilter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

class GenerateDynamicReport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 2;
    public bool $failOnTimeout = true;

    public function __construct(public string $reportId)
    {
        $this->onConnection('database-central');
        $this->onQueue('reports');
    }

    public function handle(
        DynamicReportFilterPipeline $filterPipeline,
        DynamicReportChunkContextLoader $contextLoader,
        CustomerLevelFilter $customerLevelFilter,
        DynamicReportFieldRegistry $fields,
        DynamicReportRowBuilder $rowBuilder,
    ): void
    {
        $report = DynamicReport::query()->findOrFail($this->reportId);
        $this->progress($report, ['status' => 'processing', 'progress' => 1, 'stage' => 'بررسی فیلترها و شمارش رکوردها', 'error' => null]);
        $report->rows()->delete();

        $filters = $report->filters ?? [];
        $query = $filterPipeline->query($filters);
        $total = (clone $query)->count();
        $this->progress($report, ['total_rows' => $total, 'progress' => $total ? 5 : 100]);

        if ($total === 0) {
            $this->progress($report, [
                'status' => 'completed', 'stage' => 'گزارش آماده است', 'processed_rows' => 0,
                'completed_at' => now(),
            ]);
            return;
        }

        $processed = 0;
        $matched = 0;
        $columns = array_values(array_intersect($report->columns ?? [], $fields->keys()));

        $query->chunkById(500, function (Collection $patients) use ($report, $columns, $total, $filters, $contextLoader, $customerLevelFilter, $rowBuilder, &$processed, &$matched): void {
            $now = now();
            $rows = [];

            foreach ($contextLoader->load($patients, $filters) as $context) {
                if (! $customerLevelFilter->matches($context, $filters)) continue;
                $rows[] = [
                    'report_id' => $report->id,
                    'patient_id' => $context->patient->id,
                    'payload' => json_encode($rowBuilder->build($columns, $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                ];
            }

            if ($rows) DynamicReportRow::query()->insert($rows);
            $processed += $patients->count();
            $matched += count($rows);
            $progress = min(95, 5 + (int) floor(($processed / $total) * 90));
            $this->progress($report, [
                'processed_rows' => $processed,
                'progress' => $progress,
                'stage' => "بررسی {$processed} از {$total} مراجع؛ {$matched} نتیجه",
            ]);
        }, 'id');

        $this->progress($report, [
            'status' => 'completed', 'progress' => 100, 'stage' => 'گزارش آماده است',
            'total_rows' => $matched, 'processed_rows' => $processed, 'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $report = DynamicReport::query()->find($this->reportId);
        if (! $report) return;
        $this->progress($report, [
            'status' => 'failed', 'stage' => 'ساخت گزارش ناموفق بود',
            'error' => mb_substr($exception?->getMessage() ?: 'خطای ناشناخته', 0, 2000),
        ]);
    }

    private function progress(DynamicReport $report, array $values): void
    {
        $report->update($values);
        try {
            event(DynamicReportProgressed::fromReport($report->refresh()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

}
