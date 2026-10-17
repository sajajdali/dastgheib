<?php

namespace App\Reporting\DynamicReports\Sources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ReportSource
{
    public function query(array $filters): Builder;

    /** Each result contains a rendering context and stable source IDs. */
    public function rows(Collection $records, array $filters): Collection;
}
