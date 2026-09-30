<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCustomerSegmentsWidget;
use App\Jobs\GenerateDashboardSummaryWidget;
use App\Jobs\GenerateStaffIncomeWidget;
use App\Jobs\GenerateAdvertisingRoiWidget;
use App\Jobs\GenerateLoyaltyWidget;
use App\Models\ReportWidgetSnapshot;
use App\Services\ClinicReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportWidgetController extends Controller
{
    private const CUSTOMER_SEGMENTS = 'customer-segments';
    private const DASHBOARD_SUMMARY = 'dashboard-summary';
    private const STAFF_INCOME = 'staff-income';
    private const ADVERTISING_ROI = 'advertising-roi';
    private const LOYALTY = 'loyalty';
    private const CANCELLATION_RATE = 'cancellation-rate';
    private const PHOTO_ANALYSIS = 'photo-analysis';
    private const EXPENSES = 'expenses';
    private const CUSTOMER_ACQUISITION = 'customer-acquisition';
    private const CAMPAIGN_PERFORMANCE = 'campaign-performance';
    private const ADVERTISING_CHANNELS = 'advertising-channels';
    private const DOCTOR_PERFORMANCE = 'doctor-performance';
    private const AGE_STATISTICS = 'age-statistics';
    private const CITY_STATISTICS = 'city-statistics';
    private const TOP_SERVICES = 'top-services';
    private const STAFF_APPOINTMENTS = 'staff-appointments';
    private const SATISFACTION = 'satisfaction';

    public function satisfaction(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::SATISFACTION, $filters);
        return response()->json(['snapshot'=>$snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateSatisfaction(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::SATISFACTION, $filters, $request);
        $snapshot->update([
            'filters'=>$filters,
            'result'=>$reports->satisfactionReport($filters),
            'status'=>'completed',
            'progress'=>100,
            'stage'=>'گزارش رضایت‌مندی آماده است',
            'error'=>null,
            'completed_at'=>now(),
            'requested_by'=>$request->user()?->id,
        ]);
        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function staffAppointments(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::STAFF_APPOINTMENTS, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateStaffAppointments(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::STAFF_APPOINTMENTS, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->staffAppointmentReport($filters),
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'گزارش وقت‌دهی پرسنل آماده است',
            'error' => null,
            'completed_at' => now(),
            'requested_by' => $request->user()?->id,
        ]);
        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function topServices(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::TOP_SERVICES, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateTopServices(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::TOP_SERVICES, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->topServicesReport($filters),
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'گزارش پردرآمدترین و پرسودترین خدمات آماده است',
            'error' => null,
            'completed_at' => now(),
            'requested_by' => $request->user()?->id,
        ]);
        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }
    public function cityStatistics(Request $request, ClinicReportService $reports): JsonResponse { $filters=$reports->normalizeFilters($request->only(['from','to'])); $s=$this->find(self::CITY_STATISTICS,$filters); return response()->json(['snapshot'=>$s?$this->data($s):null]); }
    public function calculateCityStatistics(Request $request, ClinicReportService $reports): JsonResponse { $filters=$reports->normalizeFilters($request->only(['from','to'])); $s=$this->prepare(self::CITY_STATISTICS,$filters,$request); $s->update(['filters'=>$filters,'result'=>$reports->cityStatisticsReport($filters),'status'=>'completed','progress'=>100,'stage'=>'آمار شهرها آماده است','error'=>null,'completed_at'=>now(),'requested_by'=>$request->user()?->id]); return response()->json(['snapshot'=>$this->data($s->fresh())]); }

    public function ageStatistics(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::AGE_STATISTICS, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateAgeStatistics(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::AGE_STATISTICS, $filters, $request);
        $snapshot->update(['filters'=>$filters,'result'=>$reports->ageStatisticsReport($filters),'status'=>'completed','progress'=>100,'stage'=>'آمار سنی آماده است','error'=>null,'completed_at'=>now(),'requested_by'=>$request->user()?->id]);
        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function doctorPerformance(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::DOCTOR_PERFORMANCE, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateDoctorPerformance(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::DOCTOR_PERFORMANCE, $filters, $request);
        $snapshot->update([
            'filters'=>$filters, 'result'=>$reports->doctorPerformanceReport($filters),
            'status'=>'completed', 'progress'=>100, 'stage'=>'گزارش عملکرد پزشکان آماده است',
            'error'=>null, 'completed_at'=>now(), 'requested_by'=>$request->user()?->id,
        ]);
        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function advertisingChannels(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::ADVERTISING_CHANNELS, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateAdvertisingChannels(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::ADVERTISING_CHANNELS, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->advertisingChannelsReport($filters),
            'status' => 'completed', 'progress' => 100,
            'stage' => 'آمار کانال‌های تبلیغاتی آماده است', 'error' => null,
            'completed_at' => now(), 'requested_by' => $request->user()?->id,
        ]);
        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function campaignPerformance(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::CAMPAIGN_PERFORMANCE, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateCampaignPerformance(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::CAMPAIGN_PERFORMANCE, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->campaignPerformanceReport($filters),
            'status' => 'completed', 'progress' => 100,
            'stage' => 'گزارش بازدهی کمپین‌ها آماده است', 'error' => null,
            'completed_at' => now(), 'requested_by' => $request->user()?->id,
        ]);
        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function customerAcquisition(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::CUSTOMER_ACQUISITION, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateCustomerAcquisition(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::CUSTOMER_ACQUISITION, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->customerAcquisitionReport($filters),
            'status' => 'completed', 'progress' => 100,
            'stage' => 'گزارش هزینه جذب آماده است', 'error' => null,
            'completed_at' => now(), 'requested_by' => $request->user()?->id,
        ]);
        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function expenses(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::EXPENSES, $filters);

        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateExpenses(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::EXPENSES, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->expensesReport($filters),
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'گزارش هزینه‌ها آماده است',
            'error' => null,
            'completed_at' => now(),
            'requested_by' => $request->user()?->id,
        ]);

        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function photoAnalysis(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::PHOTO_ANALYSIS, $filters);

        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculatePhotoAnalysis(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::PHOTO_ANALYSIS, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->photoQualityReport($filters),
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'آنالیز عکس‌ها آماده است',
            'error' => null,
            'completed_at' => now(),
            'requested_by' => $request->user()?->id,
        ]);

        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function cancellationRate(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::CANCELLATION_RATE, $filters);
        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateCancellationRate(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::CANCELLATION_RATE, $filters, $request);
        $snapshot->update([
            'filters' => $filters,
            'result' => $reports->cancellationRateReport($filters),
            'status' => 'completed',
            'progress' => 100,
            'stage' => 'گزارش نرخ کنسلی آماده است',
            'error' => null,
            'completed_at' => now(),
            'requested_by' => $request->user()?->id,
        ]);

        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function loyalty(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters=$reports->normalizeFilters($request->only(['from','to']));
        $snapshot=$this->find(self::LOYALTY,$filters);
        return response()->json(['snapshot'=>$snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateLoyalty(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters=$reports->normalizeFilters($request->only(['from','to']));
        $snapshot=$this->prepare(self::LOYALTY,$filters,$request);
        if (! in_array($snapshot->status,['queued','processing'],true)) $snapshot->update(['status'=>'queued','progress'=>0,'stage'=>'در صف محاسبه','error'=>null,'completed_at'=>null]);
        GenerateLoyaltyWidget::dispatchSync($snapshot->id);
        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function advertisingRoi(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::ADVERTISING_ROI, $filters);
        return response()->json(['snapshot'=>$snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateAdvertisingRoi(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::ADVERTISING_ROI, $filters, $request);
        if (! in_array($snapshot->status, ['queued', 'processing'], true)) {
            $snapshot->update(['status'=>'queued','progress'=>0,'stage'=>'در صف محاسبه','error'=>null,'completed_at'=>null]);
        }
        GenerateAdvertisingRoiWidget::dispatchSync($snapshot->id);
        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function staffIncomeRoster(ClinicReportService $reports): JsonResponse
    {
        return response()->json(['staff'=>$reports->staffIncomeRoster()]);
    }

    public function staffIncome(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::STAFF_INCOME, $filters);
        return response()->json(['snapshot'=>$snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateStaffIncome(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::STAFF_INCOME, $filters, $request);
        $snapshot->update(['status'=>'queued','progress'=>0,'stage'=>'در حال شروع محاسبه','error'=>null,'completed_at'=>null]);

        // This report must also work on installations where a dedicated reports
        // queue worker is not running. Running it in the request prevents a
        // snapshot from remaining queued forever and keeps retries deterministic.
        GenerateStaffIncomeWidget::dispatchSync($snapshot->id);

        return response()->json(['snapshot'=>$this->data($snapshot->fresh())]);
    }

    public function dashboardSummary(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::DASHBOARD_SUMMARY, $filters);

        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateDashboardSummary(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->prepare(self::DASHBOARD_SUMMARY, $filters, $request);
        if (! in_array($snapshot->status, ['queued', 'processing'], true)) {
            $snapshot->update(['status'=>'queued','progress'=>0,'stage'=>'در صف محاسبه','error'=>null,'completed_at'=>null]);
        }
        GenerateDashboardSummaryWidget::dispatchSync($snapshot->id);

        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    public function customerSegments(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $snapshot = $this->find(self::CUSTOMER_SEGMENTS, $filters);

        return response()->json(['snapshot' => $snapshot ? $this->data($snapshot) : null]);
    }

    public function calculateCustomerSegments(Request $request, ClinicReportService $reports): JsonResponse
    {
        $filters = $reports->normalizeFilters($request->only(['from', 'to']));
        $hash = $this->hash($filters);
        $snapshot = ReportWidgetSnapshot::query()->firstOrNew([
            'report_key' => self::CUSTOMER_SEGMENTS,
            'filter_hash' => $hash,
        ]);

        $isFresh = $snapshot->exists && $this->isFreshToday($snapshot);
        if ($isFresh && in_array($snapshot->status, ['queued', 'processing'], true)) {
            return response()->json(['snapshot' => $this->data($snapshot)], 202);
        }

        $snapshot->fill([
            'filters' => $filters,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'در صف محاسبه',
            'error' => null,
            'completed_at' => null,
            'requested_by' => $request->user()?->id,
        ])->save();

        GenerateCustomerSegmentsWidget::dispatchSync($snapshot->id);

        return response()->json(['snapshot' => $this->data($snapshot->fresh())]);
    }

    private function prepare(string $key, array $filters, Request $request): ReportWidgetSnapshot
    {
        $snapshot = ReportWidgetSnapshot::query()->firstOrCreate([
            'report_key'=>$key, 'filter_hash'=>$this->hash($filters),
        ], [
            'filters'=>$filters, 'status'=>'idle', 'progress'=>0,
            'stage'=>null, 'error'=>null, 'requested_by'=>$request->user()?->id,
        ]);

        // A widget cache is intentionally valid only for the Tehran calendar
        // day in which it was calculated. Reset stale in-progress/completed
        // state before starting a new calculation on a later day.
        if (! $snapshot->wasRecentlyCreated && ! $this->isFreshToday($snapshot)) {
            $snapshot->update([
                'filters' => $filters,
                'result' => null,
                'status' => 'idle',
                'progress' => 0,
                'stage' => null,
                'error' => null,
                'completed_at' => null,
                'requested_by' => $request->user()?->id,
            ]);
        }

        return $snapshot;
    }

    private function find(string $key, array $filters): ?ReportWidgetSnapshot
    {
        $snapshot = ReportWidgetSnapshot::query()
            ->where('report_key', $key)
            ->where('filter_hash', $this->hash($filters))
            ->first();

        return $snapshot && $this->isFreshToday($snapshot) ? $snapshot : null;
    }

    private function isFreshToday(ReportWidgetSnapshot $snapshot): bool
    {
        if (in_array($snapshot->status, ['queued', 'processing'], true)
            && $snapshot->updated_at?->lt(now()->subMinutes(3))) {
            return false;
        }

        if ($snapshot->status === 'completed') {
            return $snapshot->completed_at?->isToday() === true;
        }

        // Keep today's queued/processing/failed state visible, but never let
        // yesterday's unfinished calculation block today's Create action.
        return $snapshot->updated_at?->isToday() === true;
    }

    private function hash(array $filters): string
    {
        ksort($filters);
        return hash('sha256', json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function data(ReportWidgetSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'report_key' => $snapshot->report_key,
            'filters' => $snapshot->filters,
            'result' => $snapshot->result,
            'status' => $snapshot->status,
            'progress' => $snapshot->progress,
            'stage' => $snapshot->stage,
            'error' => $snapshot->error,
            'completed_at' => $snapshot->completed_at?->toIso8601String(),
        ];
    }
}
