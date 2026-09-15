<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDynamicReportRequest;
use App\Jobs\GenerateDynamicReport;
use App\Models\DynamicReport;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\AppointmentStatusRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\HumanResourceController;
use App\Models\InventoryAddonDefinition;
use App\Models\Doctor;
use App\Models\Staff;

class DynamicReportController extends Controller
{
    public function options(AppointmentStatusRegistry $statuses, HumanResourceController $humanResources): JsonResponse
    {
        return response()->json(['appointment_statuses' => $statuses->all(), 'areas' => collect($humanResources->serviceTags())->map(fn ($v) => trim((string) $v))->filter()->unique()->values(), 'extras' => InventoryAddonDefinition::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->values(), 'doctors' => Doctor::query()->orderBy('name')->get(['name','salary'])->map(fn($r)=>['label'=>$r->name,'meta'=>number_format((float)$r->salary,0,'.','٬')]), 'consultants' => Staff::query()->orderBy('name')->get(['name','salary'])->map(fn($r)=>['label'=>$r->name,'meta'=>number_format((float)$r->salary,0,'.','٬')])]);
    }

    public function store(StoreDynamicReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $report = DynamicReport::query()->create([
            'user_id' => $request->user()->id,
            'columns' => array_values($data['columns']),
            'filters' => $request->normalizedFilters(),
            'status' => 'queued',
            'stage' => 'در صف پردازش',
        ]);

        GenerateDynamicReport::dispatch($report->id);

        return response()->json(['report' => $this->statusData($report)], 202);
    }

    public function show(Request $request, DynamicReport $dynamicReport): JsonResponse
    {
        $this->authorizeReport($request, $dynamicReport);
        return response()->json(['report' => $this->statusData($dynamicReport->refresh())]);
    }

    public function rows(Request $request, DynamicReport $dynamicReport, DynamicReportFieldRegistry $fields): JsonResponse
    {
        $this->authorizeReport($request, $dynamicReport);
        abort_unless($dynamicReport->status === 'completed', 409, 'گزارش هنوز آماده نشده است.');
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));
        $rows = $dynamicReport->rows()->orderBy('id')->paginate($perPage);
        $amountTotal = 0;
        if (in_array('amount', $dynamicReport->columns ?? [], true)) {
            $amountTotal = $dynamicReport->rows()->cursor()->sum(function ($row) {
                return (float) str_replace([',', '٬', ' '], '', (string) data_get($row->payload, 'amount', 0));
            });
        }

        return response()->json([
            'columns' => collect($dynamicReport->columns)->map(fn ($key) => ['key' => $key, 'label' => $fields->label($key)])->values(),
            'data' => $rows->getCollection()->map(fn ($row) => $row->payload)->values(),
            'meta' => [
                'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(), 'total' => $rows->total(),
                'from' => $rows->firstItem(), 'to' => $rows->lastItem(),
            ],
            'summary' => ['amount_total' => $amountTotal],
        ]);
    }

    private function authorizeReport(Request $request, DynamicReport $report): void
    {
        abort_unless((int) $report->user_id === (int) $request->user()->id, 403);
    }

    private function statusData(DynamicReport $report): array
    {
        return $report->only(['id', 'status', 'progress', 'stage', 'total_rows', 'processed_rows', 'error', 'completed_at']);
    }

}
