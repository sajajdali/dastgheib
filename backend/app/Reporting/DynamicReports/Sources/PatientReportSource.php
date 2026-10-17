<?php

namespace App\Reporting\DynamicReports\Sources;

use App\Reporting\DynamicReports\DynamicReportChunkContextLoader;
use App\Reporting\DynamicReports\DynamicReportFilterPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PatientReportSource implements ReportSource
{
    public function __construct(
        private readonly DynamicReportFilterPipeline $pipeline,
        private readonly DynamicReportChunkContextLoader $contexts,
    ) {}

    public function query(array $filters): Builder
    {
        return $this->pipeline->query($filters);
    }

    public function rows(Collection $records, array $filters): Collection
    {
        return $this->contexts->load($records, $filters)->map(fn ($context) => [
            'context' => $context,
            'patient_id' => $context->patient->id,
            'source_id' => $context->patient->id,
        ]);
    }
}
