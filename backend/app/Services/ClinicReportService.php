<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ActivityLog;
use App\Models\AppointmentFinancialTransaction;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryAddonDefinition;
use App\Models\InventorySection;
use App\Models\PatientMedia;
use App\Models\ResourceAdjustment;
use App\Models\ResourceEarningLine;
use App\Models\SatisfactionAnswer;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Staff;
use App\Models\AppSetting;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClinicReportService
{
    public function __construct(private readonly CustomerLevelService $customerLevels) {}

    public function dashboard(array $filters, ?callable $progress = null): array
    {
        // Read appointments in bounded batches. The lazy collection can be
        // iterated repeatedly, but never hydrates the whole report range.
        $appointments = $this->appointments($filters)->lazyById(500);
        if ($progress) $progress(5, 'خواندن نوبت‌های بازه انتخابی');
        $completed = $appointments->filter(fn ($row) => $this->isCompleted($row));
        // درآمد با «انجام شد» شناسایی می‌شود (بعد از تخفیف)؛ بدهی بیمار جدا گزارش می‌شود
        // و «دریافتی» فقط پولی است که واقعاً ثبت شده (نقد، کارت، چک، کیف پول).
        $paidCompleted = $completed->filter(fn ($row) => $this->isRevenueRecognized($row));
        $statuses = $this->statusCounts($appointments);
        $revenue = $paidCompleted->sum(fn ($row) => $this->appointmentValue($row));
        $ledgerReceipts = $this->ledgerReceipts($paidCompleted->pluck('id'));
        $cash = $paidCompleted->sum(fn ($row) => $this->receivedAmount($row, $ledgerReceipts));
        $receivables = $paidCompleted->sum(fn ($row) => max(0, $this->number($row->debt ?? 0)));
        $discountTotal = $paidCompleted->sum(fn ($row) => $this->number($row->discount));
        $visitorCount = $completed->count();
        $materialPrices = $this->materialPrices();
        $materialsTotal = $paidCompleted->sum(fn ($row) => $this->appointmentMaterialCost($row, $materialPrices));
        if ($progress) $progress(22, 'محاسبه درآمد، تخفیف و مراجعین');
        $expenseQuery = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense');
        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        // خرید مواد فقط نمایش داده می‌شود؛ هزینهٔ مواد با مصرف در خدمات از سود کم می‌شود.
        $materialPurchases = (float) (clone $expenseQuery)->where('category', 'like', '%مواد%')->sum('amount');
        $expenseItems = (clone $expenseQuery)->selectRaw("COALESCE(NULLIF(category, ''), 'بدون دسته‌بندی') AS category, SUM(amount) AS amount")->groupBy('category')->get();
        $advertisingTotal = (float) $this->advertisingExpenses($filters)->sum('amount');
        $campaignExpenses = $this->advertisingExpenses($filters)->with('campaign:id,name,ui_payload')
            ->selectRaw('campaign_id, SUM(amount) AS amount')->groupBy('campaign_id')->get();
        if ($progress) $progress(34, 'جمع‌بندی هزینه‌ها و تبلیغات');
        $resourceCosts = $this->resourceCosts($filters);
        if ($progress) $progress(46, 'محاسبه هزینه پزشکان و پرسنل');
        $forecast = $this->forecast($filters, $appointments);
        if ($progress) $progress(55, 'محاسبه تخمین درآمد آینده');

        $salesTimeline = $this->salesTimeline($paidCompleted);
        $campaigns = $this->campaigns($filters, $campaignExpenses);
        $channels = $this->channels($completed, $campaignExpenses);
        if ($progress) $progress(68, 'تحلیل فروش، کمپین‌ها و کانال‌ها');
        $advertisingRoi = $this->advertisingRoi($filters);
        $customerAcquisition = $this->customerAcquisition($filters);
        $loyalty = $this->loyalty($appointments);
        $customers = $this->customers($filters);
        $customerOverview = $this->customerOverview($filters);
        if ($progress) $progress(80, 'تحلیل تبلیغات و مشتریان');
        $satisfaction = $this->satisfaction($filters);
        $photoQuality = $this->photoQuality($filters);
        $services = $this->services($paidCompleted, $materialPrices);
        $birthdays = $this->birthdays($filters);
        $capacity = $this->capacity($revenue);
        $monthlyTrends = $this->latestFourMonthRevenue();
        if ($progress) $progress(95, 'نهایی‌سازی شاخص‌های گزارش');

        return [
            'schema_version' => 3,
            'filters' => $filters,
            'kpis' => [
                'recognized_revenue' => round($revenue),
                'cash_collected' => round($cash),
                'receivables' => round($receivables),
                'material_purchases' => round($materialPurchases),
                'expenses' => round($expenseTotal),
                'doctor_cost' => round($resourceCosts['doctor_total']),
                'staff_cost' => round($resourceCosts['staff_total']),
                'materials_cost' => round($materialsTotal),
                'discount_total' => round($discountTotal),
                'visitors_count' => $visitorCount,
                'net_profit' => round($revenue - ($expenseTotal - $materialPurchases) - $resourceCosts['doctor_total'] - $resourceCosts['staff_total'] - $materialsTotal),
                'forecast_revenue' => round($forecast['value']),
            ],
            'sales_timeline' => $salesTimeline,
            'cancellation' => $statuses,
            'forecast' => $forecast,
            'expenses' => ['total'=>round($expenseTotal),'advertising_total'=>round($advertisingTotal),'items'=>$expenseItems->map(fn($row)=>['category'=>$row->category,'amount'=>round($row->amount)])->values()],
            'resources' => $resourceCosts['resources'],
            'campaigns' => $campaigns,
            'channels' => $channels,
            'advertising_roi' => $advertisingRoi,
            'customer_acquisition' => $customerAcquisition,
            'loyalty' => $loyalty,
            'customers' => $customers,
            'customer_overview' => $customerOverview,
            'satisfaction' => $satisfaction,
            'photo_quality' => $photoQuality,
            'services' => $services,
            'birthdays' => $birthdays,
            'capacity' => $capacity,
            'monthly_trends' => $monthlyTrends,
        ];
    }

    public function photoQualityReport(array $filters): array
    {
        return ['photo_quality' => $this->photoQuality($filters)->values()];
    }

    public function expensesReport(array $filters): array
    {
        $query = Expense::query()
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$filters['from'], $filters['to']]);

        $items = (clone $query)
            ->selectRaw('category, SUM(amount) AS amount')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => trim((string) $row->category) ?: 'بدون دسته‌بندی',
                'amount' => (float) $row->amount,
            ])
            ->groupBy('category')
            ->map(fn ($rows, $category) => [
                'category' => $category,
                'amount' => round((float) $rows->sum('amount')),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'total' => round((float) (clone $query)->sum('amount')),
            'items' => $items,
        ];
    }

    public function customerAcquisitionReport(array $filters): array
    {
        return $this->customerAcquisition($filters);
    }

    public function campaignPerformanceReport(array $filters): array
    {
        $acquisition = $this->customerAcquisition($filters);
        $campaigns = collect($acquisition['campaigns'] ?? [])->map(fn (array $row) => [
            'campaign_id' => $row['campaign_id'],
            'name' => $row['name'],
            'leads' => $row['leads'],
            'appointments' => $row['appointments'],
            'performance_rate' => $row['acquisition_rate'],
        ])->sortByDesc(fn (array $row) => $row['performance_rate'] ?? -1)->values();

        return [
            'leads' => $acquisition['leads'],
            'appointments' => $acquisition['appointments'],
            'performance_rate' => $acquisition['acquisition_rate'],
            'campaigns' => $campaigns->all(),
        ];
    }

    public function advertisingChannelsReport(array $filters): array
    {
        $rows = [];
        $this->appointments($filters)->lazyById(500)->each(function ($appointment) use (&$rows): void {
            if (! $this->isCompleted($appointment)) return;
            $name = trim((string) $appointment->source) ?: 'بدون کانال';
            $key = mb_strtolower($name);
            $rows[$key] ??= ['name'=>$name,'completed'=>0,'revenue'=>0.0,'cost'=>0.0];
            $rows[$key]['completed']++;
            $rows[$key]['revenue'] += $this->appointmentValue($appointment);
        });

        // هزینهٔ واقعی تبلیغات: هزینهٔ وصل به کمپین به کانال همان کمپین می‌رسد.
        $campaignChannels = $this->campaignChannels();
        $this->advertisingExpenses($filters)->get(['campaign_id', 'amount'])->each(function ($expense) use (&$rows, $campaignChannels): void {
            $name = $campaignChannels->get((int) $expense->campaign_id) ?? self::UNASSIGNED_ADVERTISING;
            $key = mb_strtolower($name);
            $rows[$key] ??= ['name'=>$name,'completed'=>0,'revenue'=>0.0,'cost'=>0.0];
            $rows[$key]['cost'] += (float) $expense->amount;
        });

        $channelMeta = Channel::query()->get()->keyBy(fn (Channel $channel) => mb_strtolower(trim((string) $channel->name)));
        $channels = collect($rows)->map(function (array $row, string $key) use ($channelMeta): array {
            $cost = (float) $row['cost'];
            $revenue = (float) $row['revenue'];
            $channel = $channelMeta->get($key);
            return [
                'name' => $row['name'],
                'completed' => (int) $row['completed'],
                'cost' => round($cost),
                'revenue' => round($revenue),
                'ratio' => $cost > 0 ? round($revenue / $cost, 2) : null,
                'icon' => $channel?->icon,
                'icon_url' => $channel?->icon_image_url,
            ];
        })->sortByDesc('completed')->values();

        return ['channels' => $channels->all()];
    }

    public function doctorPerformanceReport(array $filters): array
    {
        $doctors = Doctor::query()->orderBy('name')->get();
        $doctorByName = $doctors->keyBy(fn (Doctor $doctor) => $this->reportNameKey($doctor->name));
        $stats = $doctors->mapWithKeys(fn (Doctor $doctor) => [(string) $doctor->id => [
            'doctor_id'=>$doctor->id, 'name'=>$doctor->name, 'avatar_url'=>$doctor->avatar_url,
            'revenue'=>0.0, 'consulted'=>[], 'converted'=>[],
        ]])->all();
        $inventoryPrices = $this->materialPrices();

        foreach ($this->appointments($filters)->lazyById(500) as $appointment) {
            $patientKey = $this->reportPatientKey($appointment);
            $date = $this->dateOf($appointment);
            $assignedDoctors = $this->appointmentDoctorNames($appointment);

            if (trim((string) $appointment->done) === 'مشاوره' && $patientKey !== '') {
                foreach ($assignedDoctors as $doctorName) {
                    $doctor = $doctorByName->get($this->reportNameKey($doctorName));
                    if (! $doctor) continue;
                    $id = (string) $doctor->id;
                    $previous = $stats[$id]['consulted'][$patientKey] ?? null;
                    if ($previous === null || $date < $previous) $stats[$id]['consulted'][$patientKey] = $date;
                }
            }

            if ($this->isRevenueRecognized($appointment)) {
                $shares = $this->appointmentDoctorRevenueShares($appointment, $inventoryPrices);
                foreach ($shares as $doctorName => $amount) {
                    $doctor = $doctorByName->get($this->reportNameKey($doctorName));
                    if ($doctor) $stats[(string) $doctor->id]['revenue'] += $amount;
                }
            }
        }

        $today = (new JalaliMonthWindow)->endingToday(1)['to'];
        $consultedDoctorIds = collect($stats)->filter(fn (array $row) => $row['consulted'] !== [])->keys();
        if ($consultedDoctorIds->isNotEmpty()) {
            foreach ($this->appointments([...$filters, 'from'=>$filters['from'], 'to'=>$today])->lazyById(500) as $appointment) {
                if (! $this->isCompleted($appointment)) continue;
                $patientKey = $this->reportPatientKey($appointment);
                if ($patientKey === '') continue;
                $date = $this->dateOf($appointment);
                foreach ($this->appointmentDoctorNames($appointment) as $doctorName) {
                    $doctor = $doctorByName->get($this->reportNameKey($doctorName));
                    if (! $doctor) continue;
                    $id = (string) $doctor->id;
                    $consultedAt = $stats[$id]['consulted'][$patientKey] ?? null;
                    if ($consultedAt !== null && $date >= $consultedAt) $stats[$id]['converted'][$patientKey] = true;
                }
            }
        }

        $payments = collect($this->resourceCosts($filters)['resources'])
            ->where('type', 'doctor')->keyBy(fn (array $row) => (string) $row['id']);

        return ['doctors' => collect($stats)->map(function (array $row, string $id) use ($payments): array {
            $payment = $payments->get($id, []);
            $consultations = count($row['consulted']);
            $converted = count($row['converted']);
            return [
                'doctor_id'=>(int) $row['doctor_id'], 'name'=>$row['name'], 'avatar_url'=>$row['avatar_url'],
                'revenue'=>round($row['revenue']),
                'commission'=>(int) ($payment['commission'] ?? 0),
                'salary'=>(int) ($payment['salary'] ?? 0),
                'attendance'=>(int) ($payment['attendance'] ?? 0),
                'adjustment'=>(int) ($payment['adjustment'] ?? 0),
                'payment_total'=>(int) ($payment['total'] ?? 0),
                'consultations'=>$consultations, 'converted'=>$converted,
                'conversion_rate'=>$consultations > 0 ? round(($converted / $consultations) * 100, 1) : null,
            ];
        })->sortByDesc('conversion_rate')->values()->all()];
    }

    public function ageStatisticsReport(array $filters): array
    {
        $groups = [
            'زیر ۳۰' => ['count' => 0, 'payment' => 0, 'services' => []],
            '۳۰ تا ۴۰' => ['count' => 0, 'payment' => 0, 'services' => []],
            '۴۰ تا ۵۰' => ['count' => 0, 'payment' => 0, 'services' => []],
            'بالای ۵۰' => ['count' => 0, 'payment' => 0, 'services' => []],
        ];
        $patients = Patient::query()->whereNotNull('birth_date')->get(['id','file_number','phone','birth_date']);
        $byFile = $patients->filter(fn ($p) => trim((string) $p->file_number) !== '')->keyBy(fn ($p) => trim((string) $p->file_number));
        $byPhone = $patients->filter(fn ($p) => trim((string) $p->phone) !== '')->keyBy(fn ($p) => trim((string) $p->phone));
        $today = now();
        $ageOf = function ($birth) use ($today): ?int {
            $text = strtr(trim((string) $birth), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);
            if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) return null;
            try {
                if ((int) $m[1] > 1700) return \Carbon\Carbon::create((int)$m[1], (int)$m[2], (int)$m[3])->age;
                $gy = (int)$m[1] + 621;
                return max(0, $today->year - $gy - (($today->month < (int)$m[2] || ($today->month === (int)$m[2] && $today->day < (int)$m[3])) ? 1 : 0));
            } catch (\Throwable) { return null; }
        };
        foreach ($this->appointments($filters)->lazyById(500) as $appointment) {
            if (!$this->isRevenueRecognized($appointment)) continue;
            $patient = $byFile->get(trim((string) $appointment->file_number)) ?: $byPhone->get(trim((string) $appointment->phone));
            $age = $patient ? $ageOf($patient->birth_date) : null;
            if ($age === null || $age < 0) continue;
            $range = $age < 30 ? 'زیر ۳۰' : ($age < 40 ? '۳۰ تا ۴۰' : ($age < 50 ? '۴۰ تا ۵۰' : 'بالای ۵۰'));
            $groups[$range]['count']++;
            $groups[$range]['payment'] += $this->appointmentValue($appointment);
            foreach (collect($appointment->services ?: []) as $service) {
                $name = trim((string)($service['name'] ?? $service['title'] ?? ''));
                if ($name !== '') $groups[$range]['services'][$name] = ($groups[$range]['services'][$name] ?? 0) + 1;
            }
        }
        $totalCount = array_sum(array_column($groups, 'count'));
        $weightedAge = 0; $ageRows = [];
        foreach ($groups as $range => $row) {
            $ages = ['زیر ۳۰'=>15,'۳۰ تا ۴۰'=>35,'۴۰ تا ۵۰'=>45,'بالای ۵۰'=>60];
            $weightedAge += $ages[$range] * $row['count'];
            arsort($row['services']);
            $ageRows[] = ['range'=>$range,'count'=>$row['count'],'payment'=>round($row['payment']),'top_service'=>array_key_first($row['services']) ?: '—'];
        }
        return ['rows'=>$ageRows,'average_age'=>$totalCount ? round($weightedAge / $totalCount, 1) : 0,'total'=>$totalCount];
    }

    public function cityStatisticsReport(array $filters): array
    {
        $rows = [];
        $patients = Patient::query()->whereNotNull('city')->get(['file_number','phone','city']);
        $byFile = $patients->filter(fn($p)=>trim((string)$p->file_number)!=='')->keyBy(fn($p)=>trim((string)$p->file_number));
        $byPhone = $patients->filter(fn($p)=>trim((string)$p->phone)!=='')->keyBy(fn($p)=>trim((string)$p->phone));
        foreach ($this->appointments($filters)->lazyById(500) as $appointment) {
            if (!$this->isRevenueRecognized($appointment)) continue;
            $patient = $byFile->get(trim((string)$appointment->file_number)) ?: $byPhone->get(trim((string)$appointment->phone));
            $city = trim((string)($patient?->city ?? ''));
            if ($city === '') continue;
            $rows[$city] ??= ['city'=>$city,'count'=>0,'payment'=>0];
            $rows[$city]['count']++;
            $rows[$city]['payment'] += $this->appointmentValue($appointment);
        }
        $rows = collect($rows)->sortByDesc('count')->values();
        $max = max(1, (int)$rows->max('count'));
        return ['rows'=>$rows->map(fn($r)=>[...$r,'payment'=>round($r['payment']),'percent'=>round($r['count']/$max*100)])->all()];
    }

    private function reportNameKey(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
    }

    private function reportPatientKey($appointment): string
    {
        $file = trim((string) $appointment->file_number);
        if ($file !== '') return 'file:'.$file;
        $phone = $this->campaignPhone($appointment->phone);
        return $phone !== '' ? 'phone:'.$phone : '';
    }

    private function appointmentDoctorNames($appointment): array
    {
        $serviceDoctors = collect($appointment->services ?: [])->pluck('doctor')->map(fn ($name) => trim((string) $name))->filter();
        if ($serviceDoctors->isNotEmpty()) return $serviceDoctors->unique()->values()->all();
        return collect(preg_split('/[،,]+/u', (string) $appointment->doctor))->map(fn ($name) => trim($name))->filter()->unique()->values()->all();
    }

    private function appointmentDoctorRevenueShares($appointment, array $inventoryPrices): array
    {
        $fallbackDoctors = collect(preg_split('/[،,]+/u', (string) $appointment->doctor))->map(fn ($name) => trim($name))->filter()->unique()->values();
        $weights = [];
        foreach (collect($appointment->services ?: []) as $service) {
            $doctor = trim((string) data_get($service, 'doctor'));
            if ($doctor === '' && $fallbackDoctors->count() === 1) $doctor = (string) $fallbackDoctors->first();
            if ($doctor === '') continue;
            // سهم هر پزشک به نسبت مبلغ فروش خدماتش (بعد از تخفیف و با مازاد)، نه هزینهٔ مواد.
            $weight = collect([$service, ...collect(data_get($service, 'addons', []))->filter(fn ($addon) => is_array($addon))->all()])
                ->filter(fn ($line) => is_array($line))
                ->sum(fn (array $line) => $this->serviceNet($line, $inventoryPrices));
            $weights[$doctor] = ($weights[$doctor] ?? 0) + max(1, $weight);
        }
        if ($weights === [] && $fallbackDoctors->isNotEmpty()) {
            foreach ($fallbackDoctors as $doctor) $weights[$doctor] = 1;
        }
        $weightTotal = array_sum($weights);
        $appointmentTotal = $this->appointmentValue($appointment);
        if ($weightTotal <= 0 || $appointmentTotal <= 0) return array_fill_keys(array_keys($weights), 0.0);
        return collect($weights)->map(fn ($weight) => $appointmentTotal * $weight / $weightTotal)->all();
    }

    private function latestFourMonthRevenue(): array
    {
        $today = (new JalaliMonthWindow)->endingToday(1)['to'];
        [$year, $month] = array_map('intval', explode('-', substr($today, 0, 7)));
        $months = [];
        for ($offset = 4; $offset >= 0; $offset--) {
            $index = $year * 12 + ($month - 1) - $offset;
            $months[] = sprintf('%04d-%02d', intdiv($index, 12), ($index % 12) + 1);
        }

        $revenues = array_fill_keys($months, 0.0);
        Appointment::query()
            ->select(['id','month','done','amount','wallet_applied','debt','services','payment_details'])
            ->whereIn('month', $months)
            ->lazyById(500)
            ->each(function ($appointment) use (&$revenues): void {
                if (! $this->isRevenueRecognized($appointment)) return;
                $revenues[$appointment->month] += $this->appointmentValue($appointment);
            });

        return collect(array_slice($months, 1))->map(function (string $reportMonth, int $index) use ($months, $revenues): array {
            $previous = (float) $revenues[$months[$index]];
            $current = (float) $revenues[$reportMonth];
            return [
                'month' => $reportMonth,
                'revenue' => round($current),
                'growth_percent' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
            ];
        })->all();
    }

    public function normalizeFilters(array $input): array
    {
        $from = $this->normalizeDate($input['from'] ?? null) ?? '1400-01-01';
        $to = $this->normalizeDate($input['to'] ?? null) ?? '1499-12-29';
        abort_if($from > $to, 422, 'بازه گزارش معتبر نیست.');
        return ['from'=>$from,'to'=>$to,'doctor'=>$input['doctor'] ?? null,'consultant'=>$input['consultant'] ?? null,'campaign_id'=>filled($input['campaign_id'] ?? null) ? (int)$input['campaign_id'] : null,'status'=>array_filter((array)($input['status'] ?? [])),'done'=>array_filter((array)($input['done'] ?? []))];
    }

    public function customerSegmentsReport(array $filters, ?callable $progress = null): array
    {
        $query = $this->appointments($filters);
        $total = (clone $query)->count();
        if ($progress) $progress(2, $total ? "آماده‌سازی {$total} نوبت" : 'بازه انتخابی نوبتی ندارد');

        return $this->customerSegments($query->lazyById(500), $progress, $total)->all();
    }

    public function staffIncomeRoster(): array
    {
        return Staff::query()->orderBy('id')->get()->map(fn (Staff $staff) => [
            'id'=>$staff->id, 'name'=>$staff->name, 'avatar_url'=>$staff->avatar_url,
        ])->all();
    }

    public function staffIncomeReport(array $filters): array
    {
        $staffById = Staff::query()->get()->keyBy('id');
        $target = (float) AppSetting::getByKey('report_staff_target', 0) * 1000000;
        $rows = $this->resourceCosts($filters)['resources']->where('type', 'staff')
            ->filter(fn (array $row) => $staffById->has($row['id']))
            ->map(function (array $row) use ($staffById, $target): array {
            $staff = $staffById->get($row['id']);
            $income = (float) ($row['commission'] ?? 0);
            return [
                'id'=>$row['id'], 'name'=>$row['name'], 'avatar_url'=>$staff?->avatar_url,
                'income'=>round($income), 'salary'=>round((float) ($row['salary'] ?? 0)),
                'total_payable'=>round((float) ($row['total'] ?? 0)), 'target'=>round($target),
                'target_reached'=>$target > 0 && $income >= $target,
            ];
            })->values();

        return [
            'target'=>round($target), 'total_income'=>round((float) $rows->sum('income')),
            'reached_count'=>$rows->where('target_reached', true)->count(), 'staff'=>$rows->all(),
        ];
    }

    public function staffAppointmentReport(array $filters): array
    {
        $staff = Staff::query()->orderBy('name')->get();
        $staffByUserId = $staff->filter(fn (Staff $item) => $item->user_id !== null)
            ->keyBy(fn (Staff $item) => (string) $item->user_id);
        $rows = $staff->mapWithKeys(fn (Staff $item) => [(string) $item->id => [
            'staff_id' => $item->id,
            'name' => $item->name,
            'avatar_url' => $item->avatar_url,
            'appointments' => 0,
            'completed' => 0,
            'revenue' => 0.0,
        ]])->all();

        $this->appointments($filters)->chunkById(500, function (Collection $appointments) use (&$rows, $staffByUserId): void {
            $creators = ActivityLog::query()
                ->where('subject_type', Appointment::class)
                ->where('event', 'created')
                ->whereIn('subject_id', $appointments->pluck('id'))
                ->orderBy('id')
                ->get(['subject_id', 'user_id', 'user_name'])
                ->unique('subject_id')
                ->keyBy('subject_id');

            foreach ($appointments as $appointment) {
                $creator = $creators->get($appointment->id);
                if (! $creator) continue;
                $staffMember = $staffByUserId->get((string) ($creator->user_id ?? ''));
                $id = $staffMember ? (string) $staffMember->id : 'user:'.($creator->user_id ?: $creator->user_name);
                if (! isset($rows[$id])) {
                    $rows[$id] = [
                        'staff_id' => null,
                        'name' => trim((string) $creator->user_name) ?: 'کاربر حذف‌شده',
                        'avatar_url' => null,
                        'appointments' => 0,
                        'completed' => 0,
                        'revenue' => 0.0,
                    ];
                }
                $rows[$id]['appointments']++;
                if ($this->isCompleted($appointment)) {
                    $rows[$id]['completed']++;
                    $rows[$id]['revenue'] += $this->appointmentValue($appointment);
                }
            }
        });

        $payments = collect($this->resourceCosts($filters)['resources'])
            ->where('type', 'staff')->keyBy(fn (array $row) => (string) $row['id']);

        return ['staff' => collect($rows)->map(function (array $row, string $id) use ($payments): array {
            $payment = $payments->get($id, []);
            return [
                ...$row,
                'row_id' => $id,
                'revenue' => round($row['revenue']),
                'commission' => round((float) ($payment['commission'] ?? 0)),
                'salary' => round((float) ($payment['salary'] ?? 0)),
                'payment_total' => round((float) ($payment['total'] ?? 0)),
                'conversion_rate' => $row['appointments'] > 0
                    ? round(($row['completed'] / $row['appointments']) * 100, 1)
                    : null,
            ];
        })->sortByDesc('conversion_rate')->values()->all()];
    }

    public function advertisingRoiReport(array $filters): array
    {
        return $this->advertisingRoi($filters);
    }

    public function cancellationRateReport(array $filters): array
    {
        $query = $this->appointments($filters);
        $attended = (clone $query)->where('status', 'آمد')->count();
        $cancelled = (clone $query)->where('status', 'کنسل شد')->count();
        $noAnswer = (clone $query)->where('status', 'پاسخ نداد')->count();
        $eligible = $attended + $cancelled + $noAnswer;

        return [
            'attended' => $attended,
            'cancelled' => $cancelled + $noAnswer,
            'cancelled_only' => $cancelled,
            'no_answer' => $noAnswer,
            'eligible' => $eligible,
            'rate' => $eligible ? round((($cancelled + $noAnswer) / $eligible) * 100, 1) : 0,
        ];
    }

    public function satisfactionReport(array $filters): array
    {
        $settings = AppSetting::getByKey('satisfaction_form_settings', []);
        if (is_string($settings)) $settings = json_decode($settings, true) ?: [];
        $configured = collect($settings['questions'] ?? [])
            ->filter(fn ($question) => ($question['type'] ?? null) === 'rating')
            ->keyBy('id');

        $answers = SatisfactionAnswer::query()
            ->with('response:id,created_at,answered_on')
            ->where('question_type', 'rating')
            ->whereNotNull('score')
            ->get()
            ->filter(function ($answer) use ($filters) {
                $date = $answer->response?->answered_on ?: $answer->response?->created_at;
                if (! $date) return false;
                $jalali = $this->gregorianToJalaliDate(Carbon::parse($date));
                return $jalali >= $filters['from'] && $jalali <= $filters['to'];
            });

        $questions = $answers->groupBy('question_key')->map(function ($rows, $key) use ($configured) {
            $question = $configured->get($key, []);
            $configuredOptions = collect($question['options'] ?? []);
            $observedOptions = $rows->map(fn ($answer) => [
                'value'=>(string) ($answer->option_key ?: $answer->answer_value),
                'label'=>$answer->answer_value ?: (string) $answer->option_key,
                'score'=>(int) $answer->score,
                'active'=>true,
            ])->unique('value');
            $options = $configuredOptions
                ->concat($observedOptions)
                ->unique(fn ($option) => (string) ($option['value'] ?? $option['label'] ?? ''))
                ->map(function ($option) use ($rows) {
                    $value = (string) ($option['value'] ?? '');
                    $score = (int) ($option['score'] ?? 0);
                    $matching = $rows->filter(fn ($answer) => $value !== ''
                        ? (string) $answer->option_key === $value
                        : (int) $answer->score === $score);
                    $count = $matching->count();
                    return [
                        'value'=>$value,
                        'score'=>$score,
                        'label'=>$option['label'] ?? $matching->first()?->answer_value ?? $value,
                        'count'=>$count,
                        'percentage'=>$rows->count() ? round($count / $rows->count() * 100, 1) : 0,
                        'active'=>(bool) ($option['active'] ?? true),
                    ];
                })
                ->filter(fn ($option) => $option['active'] || $option['count'] > 0)
                ->values();
            return [
                'key'=>$key,
                'question'=>$question['title'] ?? $rows->first()->question_label,
                'total'=>$rows->count(),
                'average'=>round((float) $rows->avg('score'), 1),
                'options'=>$options,
            ];
        })->values();

        $responseAverages = $answers->groupBy('satisfaction_response_id')
            ->map(fn ($rows) => (float) $rows->avg('score'));
        $average = $responseAverages->isEmpty() ? 0 : round((float) $responseAverages->avg(), 1);

        return [
            'questions'=>$questions,
            'answers_count'=>$answers->count(),
            'responses_count'=>$responseAverages->count(),
            'average'=>$average,
            'percentage'=>$responseAverages->isEmpty() ? 0 : round($average / 5 * 100, 1),
        ];
    }

    private function gregorianToJalaliDate($date): string
    {
        $year=(int)$date->format('Y'); $month=(int)$date->format('n'); $day=(int)$date->format('j');
        $offsets=[0,31,59,90,120,151,181,212,243,273,304,334];
        $leap=$month>2?$year+1:$year;
        $days=355666+365*$year+intdiv($leap+3,4)-intdiv($leap+99,100)+intdiv($leap+399,400)+$day+$offsets[$month-1];
        $jy=-1595+33*intdiv($days,12053); $days%=12053; $jy+=4*intdiv($days,1461); $days%=1461;
        if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
        $jm=$days<186?1+intdiv($days,31):7+intdiv($days-186,30);
        $jd=1+($days<186?$days%31:($days-186)%30);
        return sprintf('%04d-%02d-%02d',$jy,$jm,$jd);
    }

    public function loyaltyReport(array $filters, ?callable $progress = null): array
    {
        $result = [];
        foreach ([3, 6] as $index => $months) {
            $to = $filters['to'];
            [$year, $month] = array_map('intval', explode('-', substr($to, 0, 7)));
            $absolute = $year * 12 + ($month - 1) - ($months - 1);
            $from = sprintf('%04d-%02d-01', intdiv($absolute, 12), ($absolute % 12) + 1);
            $periodFilters = [...$filters, 'from'=>$from, 'to'=>$to];
            $visits = [];
            foreach ($this->appointments($periodFilters)->lazyById(500) as $appointment) {
                if (! $this->isRevenueRecognized($appointment)) continue;
                $key = $appointment->file_number ? 'f:'.$appointment->file_number : ($appointment->phone ? 'p:'.$appointment->phone : null);
                if ($key) $visits[$key] = ($visits[$key] ?? 0) + 1;
            }
            $total = count($visits);
            $returned = count(array_filter($visits, fn (int $count) => $count >= 2));
            $result[(string) $months] = [
                'months'=>$months,'from'=>$from,'to'=>$to,'total'=>$total,'returned'=>$returned,
                'churned'=>max(0,$total-$returned),'return_rate'=>$total ? round($returned/$total*100,1) : 0,
            ];
            if ($progress) $progress($index === 0 ? 55 : 95, $index === 0 ? 'محاسبه بازگشت شش‌ماهه' : 'نهایی‌سازی نرخ بازگشت و ریزش');
        }
        return $result;
    }

    public function drilldown(string $metric, array $filters): array
    {
        if (in_array($metric, ['recognized_revenue', 'cash_collected', 'forecast_revenue'], true)) {
            $rows = $this->appointments($filters)->get()->filter(fn ($row) => $metric === 'forecast_revenue' ? str_contains((string) $row->status, 'وقت') : $this->isRevenueRecognized($row));
            $ledgerReceipts = $metric === 'cash_collected' ? $this->ledgerReceipts($rows->pluck('id')) : collect();
            $amount = fn ($row) => $metric === 'cash_collected'
                ? $this->receivedAmount($row, $ledgerReceipts)
                : $this->appointmentValue($row);
            return ['metric'=>$metric, 'rows'=>$rows->map(fn ($row) => ['id'=>$row->id,'date'=>$this->dateOf($row),'patient'=>trim(($row->firstname ?? '').' '.($row->lastname ?? '')),'status'=>$row->status,'done'=>$row->done,'amount'=>round($amount($row))])->values(), 'total'=>round($rows->sum($amount))];
        }
        if ($metric === 'expenses') {
            $rows = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense')->get();
            return ['metric'=>$metric,'rows'=>$rows->map(fn($row)=>['id'=>$row->id,'date'=>$row->occurred_on,'title'=>$row->title,'category'=>$row->category,'amount'=>(float)$row->amount,'campaign_id'=>$row->campaign_id])->values(),'total'=>round($rows->sum('amount'))];
        }
        if ($metric === 'cancellation') {
            $rows = $this->appointments($filters)->get()->filter(fn ($row) => in_array(trim((string) $row->status), ['آمد', 'کنسل شد', 'پاسخ نداد'], true));
            return ['metric'=>$metric,'rows'=>$rows->map(fn($row)=>['id'=>$row->id,'date'=>$this->dateOf($row),'patient'=>trim(($row->firstname ?? '').' '.($row->lastname ?? '')),'status'=>$row->status])->values(),'summary'=>$this->statusCounts($rows)];
        }
        abort(404, 'جزئیات این شاخص تعریف نشده است.');
    }

    public function topServicesReport(array $filters): array
    {
        $appointments = $this->appointments($filters)
            ->where('done', 'انجام شد')
            ->get()
            ->filter(fn ($appointment) => $this->isRevenueRecognized($appointment));

        if ($appointments->isEmpty()) {
            return ['rows' => [], 'totals' => ['revenue' => 0, 'material_cost' => 0, 'commission' => 0, 'profit' => 0]];
        }

        $appointmentIds = $appointments->pluck('id');
        $earningLines = ResourceEarningLine::query()
            ->whereIn('appointment_id', $appointmentIds)
            ->where('status', 'active')
            ->whereIn('resource_type', ['doctor', 'staff'])
            ->get()
            ->groupBy('appointment_id');

        $serviceNames = $appointments->flatMap(function (Appointment $appointment) {
            return collect($appointment->services ?: [])->flatMap(function ($service) {
                $service = is_array($service) ? $service : [];
                return collect([$service['name'] ?? $service['service'] ?? null])
                    ->merge(collect($service['addons'] ?? [])->map(fn ($addon) => is_array($addon) ? ($addon['name'] ?? null) : null));
            });
        })->map(fn ($name) => trim((string) $name))->filter()->unique()->values();
        $inventoryIds = $appointments->flatMap(function (Appointment $appointment) {
            return collect($appointment->services ?: [])->flatMap(function ($service) {
                $service = is_array($service) ? $service : [];
                return collect([$service['inventory_id'] ?? null])
                    ->merge(collect($service['addons'] ?? [])->map(fn ($addon) => is_array($addon) ? ($addon['inventory_id'] ?? null) : null));
            });
        })->filter()->unique()->values();

        $inventoryRows = Inventory::query()->where(function (Builder $query) use ($serviceNames, $inventoryIds) {
            $query->whereIn('name', $serviceNames);
            if ($inventoryIds->isNotEmpty()) $query->orWhereIn('id', $inventoryIds);
        })->get();
        $inventoriesByName = $inventoryRows->keyBy('name');
        $inventoriesById = $inventoryRows->keyBy('id');
        $materialPrices = $this->materialPrices();
        $rows = [];

        foreach ($appointments as $appointment) {
            $appointmentLines = $earningLines->get($appointment->id, collect());
            $calculatedLines = [];
            foreach (array_values($appointment->services ?: []) as $index => $service) {
                $service = is_array($service) ? $service : [];
                $lines = [[$service, false]];
                foreach (array_values($service['addons'] ?? []) as $addon) {
                    $lines[] = [is_array($addon) ? $addon : [], true];
                }

                foreach ($lines as [$line, $isAddon]) {
                    $name = trim((string) ($line['name'] ?? $line['service'] ?? ''));
                    if ($name === '') continue;

                    $matched = $appointmentLines->filter(fn (ResourceEarningLine $earning) =>
                        (int) $earning->service_line_index === $index
                        && (bool) $earning->is_addon === $isAddon
                        && trim((string) $earning->service_name) === $name
                    );
                    $inventory = (! empty($line['inventory_id']) ? $inventoriesById->get((int) $line['inventory_id']) : null)
                        ?: $inventoriesByName->get($name);
                    $quantity = $this->serviceQuantity($line);
                    if ($quantity <= 0) continue;
                    $gross = $matched->isNotEmpty()
                        ? (float) $matched->max('gross_amount')
                        : $this->number($line['price'] ?? $line['amount'] ?? $inventory?->amount ?? 0) * $quantity;
                    $adjustment = max(0, $this->number($line['discount'] ?? 0));
                    $isSurcharge = ($line['adjustment_mode'] ?? '') === 'surcharge';
                    $revenue = $matched->isNotEmpty()
                        ? (float) $matched->max('net_amount')
                        : max(0, $gross + ($isSurcharge ? $adjustment : -min($adjustment, $gross)));
                    $materialCost = $matched->isNotEmpty()
                        ? (float) $matched->max('material_cost')
                        : $this->serviceMaterialCost([...$line, 'addons' => []], $materialPrices);
                    $commission = (float) $matched->sum('amount');

                    $calculatedLines[] = compact('name', 'quantity', 'revenue', 'materialCost', 'commission');
                }
            }

            // Appointment amount is already net of service discounts. Wallet is
            // a payment method, so add it back; debt never changes recognized revenue.
            $rawRevenue = collect($calculatedLines)->sum('revenue');
            // همان درآمد نوبت در خلاصهٔ گزارش، تا جمع خدمات با درآمد کل یکی باشد.
            $targetRevenue = $this->appointmentValue($appointment);
            $weightTotal = collect($calculatedLines)->sum(fn (array $line) => max(1, $line['quantity']));

            foreach ($calculatedLines as $calculated) {
                $revenue = $rawRevenue > 0
                    ? $targetRevenue * ($calculated['revenue'] / $rawRevenue)
                    : ($weightTotal > 0 ? $targetRevenue * (max(1, $calculated['quantity']) / $weightTotal) : 0);
                $name = $calculated['name'];
                $rows[$name] ??= ['name'=>$name, 'count'=>0, 'revenue'=>0, 'material_cost'=>0, 'commission'=>0, 'profit'=>0];
                $rows[$name]['count'] += $calculated['quantity'];
                $rows[$name]['revenue'] += $revenue;
                $rows[$name]['material_cost'] += $calculated['materialCost'];
                $rows[$name]['commission'] += $calculated['commission'];
                $rows[$name]['profit'] += $revenue - $calculated['materialCost'] - $calculated['commission'];
            }
        }

        $result = collect($rows)->map(fn (array $row) => [
            ...$row,
            'count' => round($row['count'], 3),
            'revenue' => round($row['revenue']),
            'material_cost' => round($row['material_cost']),
            'commission' => round($row['commission']),
            'profit' => round($row['profit']),
        ])->sortByDesc('revenue')->values();

        return [
            'rows' => $result,
            'sections' => $this->sectionTotals($result),
            'totals' => [
                'revenue' => (int) $result->sum('revenue'),
                'material_cost' => (int) $result->sum('material_cost'),
                'commission' => (int) $result->sum('commission'),
                'profit' => (int) $result->sum('profit'),
            ],
        ];
    }

    /** درآمد و سود هر بخش اصلی خدمات (گروه بالادستی در انبار) از جمع خدمات همان بخش. */
    private function sectionTotals(Collection $serviceRows): array
    {
        $sections = InventorySection::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $rootName = function (?int $sectionId) use ($sections): string {
            $section = $sectionId ? $sections->get($sectionId) : null;
            for ($guard = 0; $section && $section->parent_id && $guard < 10; $guard++) {
                $section = $sections->get($section->parent_id) ?? $section;
            }
            return trim((string) ($section?->name ?? '')) ?: 'بدون بخش';
        };
        $sectionByService = Inventory::query()->whereIn('name', $serviceRows->pluck('name'))->get(['name', 'section_id'])
            ->mapWithKeys(fn (Inventory $item) => [$item->name => $rootName($item->section_id)]);

        return $serviceRows->groupBy(fn (array $row) => $sectionByService->get($row['name'], 'بدون بخش'))
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'revenue' => (int) $rows->sum('revenue'),
                'material_cost' => (int) $rows->sum('material_cost'),
                'commission' => (int) $rows->sum('commission'),
                'profit' => (int) $rows->sum('profit'),
            ])->filter(fn (array $row) => $row['revenue'] != 0 || $row['profit'] != 0)->sortByDesc('revenue')->values()->all();
    }

    private function appointments(array $filters): Builder
    {
        return Appointment::query()->select([
            'id', 'month', 'day_num', 'lastname', 'phone', 'file_number',
            'status', 'done', 'amount', 'wallet_applied', 'debt', 'discount', 'services', 'payment_details', 'source', 'campaign_id',
            'new_customer', 'doctor', 'consultant', 'completed_at',
        ])->where(function(Builder $q) use($filters) {
            [$fm,$fd]=[substr($filters['from'],0,7),(int)substr($filters['from'],8,2)]; [$tm,$td]=[substr($filters['to'],0,7),(int)substr($filters['to'],8,2)];
            $q->where(fn($x)=>$x->where('month','>',$fm)->orWhere(fn($y)=>$y->where('month',$fm)->where('day_num','>=',$fd)))
              ->where(fn($x)=>$x->where('month','<',$tm)->orWhere(fn($y)=>$y->where('month',$tm)->where('day_num','<=',$td)));
        })->when($filters['doctor'],fn($q,$v)=>$q->where('doctor',$v))->when($filters['consultant'],fn($q,$v)=>$q->where('consultant',$v))->when($filters['campaign_id'],fn($q,$v)=>$q->where('campaign_id',$v))->when($filters['status'],fn($q,$v)=>$q->whereIn('status',$v))->when($filters['done'],fn($q,$v)=>$q->whereIn('done',$v));
    }

    private function statusCounts($rows): array
    {
        $arrived=$rows->filter(fn($r)=>trim((string) $r->status)==='آمد')->count();
        $cancelled=$rows->filter(fn($r)=>trim((string) $r->status)==='کنسل شد')->count();
        $noAnswer=$rows->filter(fn($r)=>trim((string) $r->status)==='پاسخ نداد')->count();
        $base=$arrived+$cancelled+$noAnswer;
        return ['arrived'=>$arrived,'cancelled'=>$cancelled,'no_answer'=>$noAnswer,'eligible'=>$base,'rate'=>$base?round((($cancelled+$noAnswer)/$base)*100,1):0];
    }

    private function forecast(array $filters, $appointments): array
    {
        $today=(new JalaliMonthWindow)->endingToday(1)['to'];
        $month=substr($today,0,7); [$year,$m]=array_map('intval',explode('-',$month)); $previous=sprintf('%04d-%02d',$m===1?$year-1:$year,$m===1?12:$m-1);
        $prior=Appointment::query()->where('month',$previous)->select(['id','status','amount','services'])->lazyById(500); $rate=$this->statusCounts($prior)['rate']/100;
        $scheduled=$appointments->filter(fn($r)=>str_contains((string)$r->status,'وقت') && $this->dateOf($r)>=$today);
        $gross=$scheduled->sum(fn($r)=>$this->appointmentValue($r));
        return ['scheduled_value'=>round($gross),'previous_month_cancellation_rate'=>round($rate*100,1),'value'=>round($gross*(1-$rate))];
    }

    private function resourceCosts(array $filters): array
    {
        $months = $this->monthsBetween($filters['from'], $filters['to']);
        // پورسانت فقط برای نوبت‌های همین بازه؛ ردیف‌های ماهانه (پاداش پلکانی و حضور و غیاب) کامل می‌آیند.
        $rangeAppointmentIds = $this->appointments($filters)->pluck('id');
        $lines = ResourceEarningLine::query()
            ->whereIn('month', $months)
            ->where('status', 'active')
            ->where(fn (Builder $nested) => $nested->whereNull('appointment_id')->orWhereIn('appointment_id', $rangeAppointmentIds))
            ->get()
            ->groupBy(fn ($row) => $row->resource_type.'-'.$row->resource_id);
        $adjustments = ResourceAdjustment::query()->whereIn('month', $months)->get()
            ->groupBy(fn ($row) => $row->resource_type.'-'.$row->resource_id);
        $resources = collect();
        foreach (Doctor::query()->get() as $doctor) $resources->put('doctor-'.$doctor->id, ['type'=>'doctor','id'=>$doctor->id,'name'=>$doctor->name,'base_salary'=>$this->number($doctor->salary)]);
        foreach (Staff::query()->get() as $staff) $resources->put('staff-'.$staff->id, ['type'=>'staff','id'=>$staff->id,'name'=>$staff->name,'base_salary'=>$this->number($staff->salary)]);
        foreach ($lines as $key => $items) if (! $resources->has($key)) $resources->put($key, ['type'=>$items->first()->resource_type,'id'=>$items->first()->resource_id,'name'=>$items->first()->resource_name,'base_salary'=>0]);
        foreach ($adjustments as $key => $items) if (! $resources->has($key)) $resources->put($key, ['type'=>$items->first()->resource_type,'id'=>$items->first()->resource_id,'name'=>'پرسنل حذف‌شده','base_salary'=>0]);
        $salaryRatio = $this->salaryRatio($filters);
        $target = (float) AppSetting::getByKey('report_staff_target', 0);
        $rows = $resources->map(function ($resource, $key) use ($lines, $adjustments, $salaryRatio, $target) {
            $items = $lines->get($key, collect());
            $savedSalary = (float) $items->where('earning_type', 'salary')->sum('amount');
            $salary = $savedSalary ?: $resource['base_salary'] * $salaryRatio;
            $commission = (float) $items->whereIn('earning_type', [...ResourceEarningLine::COMMISSION_TYPES, 'sales_bonus'])->sum('amount');
            $attendance = (float) $items->whereIn('earning_type', ['attendance_overtime','attendance_shortage','attendance_absence'])->sum('amount');
            $adjustment = (float) $adjustments->get($key, collect())->sum('amount');
            $total = $salary + $commission + $attendance + $adjustment;
            return ['type'=>$resource['type'],'id'=>$resource['id'],'name'=>$resource['name'],'salary'=>round($salary),'commission'=>round($commission),'attendance'=>round($attendance),'adjustment'=>round($adjustment),'total'=>round($total),'target'=>$resource['type']==='staff' ? round($target) : null,'target_reached'=>$resource['type']==='staff' && $target > 0 ? $commission >= $target : null];
        })->values();
        return ['resources'=>$rows,'doctor_total'=>$rows->where('type','doctor')->sum('total'),'staff_total'=>$rows->where('type','staff')->sum('total')];
    }

    private function campaigns(array $filters, Collection $expenses): Collection
    {
        $appointmentCounts = $this->appointments($filters)
            ->whereNotNull('campaign_id')
            ->select('campaign_id')
            ->selectRaw('COUNT(*) AS aggregate_count')
            ->groupBy('campaign_id')
            ->pluck('aggregate_count', 'campaign_id');
        $expenseRows = $expenses->whereNotNull('campaign_id')->groupBy(fn ($row) => (string) $row->campaign_id);

        return Campaign::query()->where(fn (Builder $query) => $query->whereNull('note')->orWhere('note', '!=', '__system_general_followups__'))->get()->map(function (Campaign $campaign) use ($appointmentCounts, $expenseRows): array {
            $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
            $rows = collect($payload['rows'] ?? [])->filter(fn ($row) => collect([
                'fullName','phone','contactDate','followUpDate','gender','consultant',
                'description','source','status','interest','reason',
            ])->contains(fn ($key) => trim((string) data_get($row, $key, '')) !== ''));
            $scoredRows = $rows->filter(fn ($row) => filled(data_get($row, 'status')) || filled(data_get($row, 'interest')));
            $score = $scoredRows->sum(function ($row): int {
                $points = match ((string) data_get($row, 'status')) {
                    'پاسخ داد' => 20, 'پیگیری' => 10, 'اشتباه' => -5, default => 0,
                };
                $interest = $this->normalizeCampaignInterest(data_get($row, 'interest'));
                return $points + match ($interest) {
                    '1' => 15, '2' => 35, '3' => 60, 'ok' => 80, default => 0,
                };
            });
            $quality = $scoredRows->isNotEmpty() ? max(0, min(100, round($score / $scoredRows->count()))) : 0;
            $interestCounts = ['1' => 0, '2' => 0, '3' => 0];
            foreach ($rows as $row) {
                $interest = $this->normalizeCampaignInterest(data_get($row, 'interest'));
                if (array_key_exists($interest, $interestCounts)) $interestCounts[$interest]++;
            }
            $campaignAppointments = (int) ($appointmentCounts[$campaign->id] ?? 0);
            $cost = (float) $expenseRows->get((string) $campaign->id, collect())->sum('amount');

            return [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'date' => $campaign->starts_on,
                'cost' => round($cost),
                'appointments' => $campaignAppointments,
                'cac' => $campaignAppointments > 0 ? round($cost / $campaignAppointments) : null,
                'quality' => $quality,
                'quality_label' => $quality >= 75 ? 'عالی' : ($quality >= 50 ? 'خوب' : ($quality >= 25 ? 'متوسط' : 'ضعیف')),
                'leads' => $rows->count(),
                'interest_counts' => $interestCounts,
            ];
        })->sortByDesc('quality')->values();
    }

    private function normalizeCampaignInterest(mixed $value): string
    {
        $value = trim((string) $value);
        if (in_array($value, ['1','کم','low'], true)) return '1';
        if (in_array($value, ['2','متوسط','medium'], true)) return '2';
        if (in_array($value, ['3','زیاد','high'], true)) return '3';
        if (in_array($value, ['ok','وقت داده شد','وقت داده‌شد','نوبت داده شد'], true)) return 'ok';
        return '';
    }
    private function channels($completed, Collection $expenses): Collection
    {
        $rows = [];
        foreach ($completed as $appointment) {
            if (! $this->isCompleted($appointment)) continue;
            $name = trim((string) $appointment->source) ?: 'بدون کانال';
            $rows[$name] ??= ['name'=>$name,'completed'=>0,'revenue'=>0,'cost'=>0,'cost_per_completed'=>null,'ratio'=>null];
            $rows[$name]['completed']++;
            $rows[$name]['revenue'] += $this->appointmentValue($appointment);
        }
        foreach ($expenses as $expense) {
            $payload = is_array($expense->campaign?->ui_payload) ? $expense->campaign->ui_payload : [];
            $name = $expense->campaign_id
                ? (trim((string) ($payload['source'] ?? $payload['sourceName'] ?? '')) ?: 'بدون کانال')
                : self::UNASSIGNED_ADVERTISING;
            $rows[$name] ??= ['name'=>$name,'completed'=>0,'revenue'=>0,'cost'=>0,'cost_per_completed'=>null,'ratio'=>null];
            $rows[$name]['cost'] += (float) $expense->amount;
        }
        return collect($rows)->map(function ($row): array {
            $completedCount = (int) $row['completed'];
            $cost = (float) $row['cost'];
            $revenue = (float) $row['revenue'];
            return [
                ...$row,
                'cost' => round($cost),
                'revenue' => round($revenue),
                'cost_per_completed' => $completedCount > 0 ? round($cost / $completedCount) : null,
                'ratio' => $cost > 0 ? round($revenue / $cost, 2) : null,
            ];
        })->sortByDesc('completed')->values();
    }
    private function advertisingRoi(array $filters): array
    {
        $costs = $this->advertisingExpenses($filters)
            ->selectRaw("SUBSTRING(occurred_on, 1, 7) AS report_month, SUM(amount) AS total")
            ->groupBy('report_month')
            ->pluck('total', 'report_month');
        // درآمد تبلیغات: نوبت‌های انجام‌شده‌ای که کانالشان کانال یکی از کمپین‌هاست (مثلاً اینستاگرام).
        $advertisingChannels = $this->campaignChannels()->map(fn ($name) => mb_strtolower(trim($name)))->unique();
        $revenues = [];
        $this->appointments($filters)->lazyById(500)->each(function ($appointment) use (&$revenues, $advertisingChannels) {
            if (! $this->isCompleted($appointment)) return;
            if (! $advertisingChannels->contains(mb_strtolower(trim((string) $appointment->source)))) return;
            $month = (string) $appointment->month;
            $revenues[$month] = ($revenues[$month] ?? 0) + $this->appointmentValue($appointment);
        });
        $timeline = collect($this->monthsBetween($filters['from'], $filters['to']))->map(fn ($month) => [
            'month' => $month,
            'cost' => round((float) ($costs[$month] ?? 0)),
            'revenue' => round((float) ($revenues[$month] ?? 0)),
        ]);
        $cost = (float) $timeline->sum('cost');
        $revenue = (float) $timeline->sum('revenue');

        return [
            'cost' => round($cost),
            'revenue' => round($revenue),
            'ratio' => $cost > 0 ? round($revenue / $cost, 2) : null,
            'returned' => $cost > 0 && $revenue >= $cost,
            'timeline' => $timeline,
        ];
    }

    private function customerAcquisition(array $filters): array
    {
        $campaignCosts = $this->advertisingExpenses($filters)->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(amount) AS total')->groupBy('campaign_id')->pluck('total', 'campaign_id');
        $campaigns = Campaign::query()
            ->where(fn (Builder $query) => $query->whereNull('note')->orWhere('note', '!=', '__system_general_followups__'))
            ->get(['id', 'name', 'budget', 'ui_payload'])
            ->map(function (Campaign $campaign) use ($filters, $campaignCosts): array {
            $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
            $rows = collect($payload['rows'] ?? [])->filter(function ($row) use ($filters): bool {
                $date = $this->campaignContactDate(data_get($row, 'contactDate'));
                return $date !== null && $date >= $filters['from'] && $date <= $filters['to'];
            });
            $uniqueLeads = $rows->map(fn ($row) => $this->campaignPhone(data_get($row, 'phone')))->filter()->unique();
            $bookedPhones = $rows
                ->filter(fn ($row) => $this->normalizeCampaignInterest(data_get($row, 'interest')) === 'ok')
                ->map(fn ($row) => $this->campaignPhone(data_get($row, 'phone')))
                ->filter()
                ->unique();
            // هزینهٔ واقعی ثبت‌شده برای کمپین؛ بودجه فقط برای مقایسه برمی‌گردد.
            $cost = (float) ($campaignCosts[$campaign->id] ?? 0);
            $appointments = $bookedPhones->count();
            $leadCount = $uniqueLeads->count();

            return [
                'campaign_id' => (int) $campaign->id,
                'name' => $campaign->name ?: 'کمپین',
                'advertising_cost' => round($cost),
                'budget' => round((float) $campaign->budget),
                'appointments' => $appointments,
                'cost_per_appointment' => $appointments > 0 ? round($cost / $appointments) : null,
                'leads' => $leadCount,
                'acquisition_rate' => $leadCount > 0 ? round(($appointments / $leadCount) * 100, 1) : null,
            ];
        // The selected range is based on contact rows. A campaign with no
        // uniquely identifiable lead in the range must not contribute its
        // full creation cost to that range.
        })->filter(fn (array $row) => $row['leads'] > 0)->values();
        // کل هزینهٔ تبلیغات بازه، شامل هزینه‌هایی که به کمپین خاصی وصل نشده‌اند.
        $totalCost = (float) $this->advertisingExpenses($filters)->sum('amount');
        $totalAppointments = (int) $campaigns->sum('appointments');
        $totalLeads = (int) $campaigns->sum('leads');

        return [
            'advertising_cost' => round($totalCost),
            'appointments' => $totalAppointments,
            'cost_per_appointment' => $totalAppointments > 0 ? round($totalCost / $totalAppointments) : null,
            'leads' => $totalLeads,
            'acquisition_rate' => $totalLeads > 0 ? round(($totalAppointments / $totalLeads) * 100, 1) : null,
            'campaigns' => $campaigns->all(),
        ];
    }

    private function campaignPhone(mixed $value): string
    {
        return preg_replace('/\D+/', '', strtr(trim((string) $value), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ])) ?: '';
    }

    private function campaignContactDate(mixed $value): ?string
    {
        $date = strtr(trim((string) $value), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-',
        ]);
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $parts)) return null;
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        if ($year < 1700) return sprintf('%04d-%02d-%02d', $year, $month, $day);
        if (! checkdate($month, $day, $year)) return null;

        $offsets = [0,31,59,90,120,151,181,212,243,273,304,334];
        $leapYear = $month > 2 ? $year + 1 : $year;
        $days = 355666 + 365 * $year + intdiv($leapYear + 3, 4) - intdiv($leapYear + 99, 100)
            + intdiv($leapYear + 399, 400) + $day + $offsets[$month - 1];
        $jalaliYear = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jalaliYear += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jalaliYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $jalaliMonth = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jalaliDay = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);
        return sprintf('%04d-%02d-%02d', $jalaliYear, $jalaliMonth, $jalaliDay);
    }
    private function customerSegments($appointments, ?callable $progress = null, int $appointmentTotal = 0): Collection
    {
        $levels = collect([
            'silver' => ['key' => 'silver', 'label' => 'نقره‌ای', 'count' => 0, 'revenue' => 0],
            'blue' => ['key' => 'blue', 'label' => 'آبی', 'count' => 0, 'revenue' => 0],
            'gold' => ['key' => 'gold', 'label' => 'طلایی (CIP)', 'count' => 0, 'revenue' => 0],
            'problematic' => ['key' => 'problematic', 'label' => 'دردسرساز', 'count' => 0, 'revenue' => 0],
        ]);

        $customers = [];
        $processedAppointments = 0;
        foreach ($appointments as $appointment) {
            $processedAppointments++;
            $key = $appointment->file_number
                ? 'f:'.(string) $appointment->file_number
                : ($appointment->phone ? 'p:'.(string) $appointment->phone : null);
            if (! $key) continue;
            $customers[$key] ??= 0;
            if ($this->isRevenueRecognized($appointment)) $customers[$key] += $this->appointmentValue($appointment);
            if ($progress && $processedAppointments % 500 === 0) {
                $percent = $appointmentTotal ? 5 + (int) floor(($processedAppointments / $appointmentTotal) * 30) : 35;
                $progress(min(35, $percent), "بررسی {$processedAppointments} از {$appointmentTotal} نوبت");
            }
        }
        if ($customers === []) return $levels->values();

        // Scan patient records in small batches as well. Only matching patients
        // are decorated, and both patient models and their history are released
        // before the next batch is read.
        $patientTotal = Patient::query()->count();
        $processedPatients = 0;
        $matchedKeys = [];
        Patient::query()->select(['id', 'file_number', 'phone', 'customer_level'])
            ->chunkById(100, function (Collection $patients) use (&$levels, $customers, $progress, $patientTotal, &$processedPatients, &$matchedKeys) {
                $matching = $patients->filter(fn (Patient $patient) =>
                    ($patient->file_number && array_key_exists('f:'.$patient->file_number, $customers))
                    || ($patient->phone && array_key_exists('p:'.$patient->phone, $customers))
                )->values();
                $matching->chunk(25)->each(fn (Collection $chunk) => $this->customerLevels->decorate($chunk));
                foreach ($matching as $patient) {
                    // نوبت‌های یک بیمار ممکن است با پرونده یا فقط با تلفن ثبت شده باشند؛ هر دو جمع می‌شوند.
                    $keys = collect([$patient->file_number ? 'f:'.$patient->file_number : null, $patient->phone ? 'p:'.$patient->phone : null])
                        ->filter(fn ($key) => $key && array_key_exists($key, $customers) && ! array_key_exists($key, $matchedKeys));
                    if ($keys->isEmpty()) continue;
                    $level = in_array($patient->customer_level, ['silver', 'blue', 'gold', 'problematic'], true)
                        ? $patient->customer_level
                        : 'silver';
                    $row = $levels[$level];
                    $row['count']++;
                    $row['revenue'] += $keys->sum(fn ($key) => $customers[$key]);
                    $levels[$level] = $row;
                    $keys->each(function ($key) use (&$matchedKeys) { $matchedKeys[$key] = true; });
                }
                $processedPatients += $patients->count();
                if ($progress && ($processedPatients % 500 === 0 || $processedPatients >= $patientTotal)) {
                    $percent = $patientTotal ? 40 + (int) floor(($processedPatients / $patientTotal) * 55) : 95;
                    $progress(min(95, $percent), "درجه‌بندی {$processedPatients} از {$patientTotal} مشتری");
                }
            });

        // مشتری‌ای که پرونده ندارد سطح خاصی ندارد و «نقره‌ای» حساب می‌شود؛ وگرنه درآمدش از گزارش جا می‌ماند.
        $unmatched = array_diff_key($customers, $matchedKeys);
        if ($unmatched !== []) {
            $row = $levels['silver'];
            $row['count'] += count($unmatched);
            $row['revenue'] += array_sum($unmatched);
            $levels['silver'] = $row;
        }

        return $levels->map(function (array $row) {
            $row['revenue'] = round($row['revenue']);
            return $row;
        })->values();
    }
    private function loyalty($appointments): array
    {
        $currentKeys = $appointments
            ->filter(fn ($row) => $this->isRevenueRecognized($row))
            ->map(fn ($row) => $row->file_number ?: $row->phone)
            ->filter()->unique()->values();
        $customerKey = "COALESCE(NULLIF(file_number, ''), phone)";
        $paidHistory = $currentKeys->isEmpty() ? collect() : Appointment::query()
            ->where('done', 'انجام شد')
            ->whereIn(DB::raw($customerKey), $currentKeys->all())
            ->get(['id','file_number','phone','done','amount','wallet_applied','debt','services','payment_details'])
            ->filter(fn ($appointment) => $this->isRevenueRecognized($appointment));
        $loyal = $paidHistory
            ->groupBy(fn ($appointment) => $appointment->file_number ?: $appointment->phone)
            ->filter(fn ($visits) => $visits->count() >= 2)
            ->count();
        $allCompletedCustomers = Appointment::query()
            ->where('done', 'انجام شد')
            ->whereNotNull(DB::raw($customerKey))
            ->get(['id','file_number','phone','done','amount','wallet_applied','debt','services','payment_details'])
            ->filter(fn ($appointment) => $this->isRevenueRecognized($appointment))
            ->map(fn ($appointment) => $appointment->file_number ?: $appointment->phone)
            ->filter()->unique()->count();

        return [
            'completed_customers' => $currentKeys->count(),
            'loyal' => $loyal,
            'churned' => max(0, $allCompletedCustomers - $currentKeys->count()),
        ];
    }
    private function customers(array $filters): array { $rows=$this->appointments($filters)->lazyById(500); return ['new'=>$rows->where('new_customer',true)->unique(fn($r)=>$r->file_number ?: $r->phone)->count(),'old'=>$rows->where('new_customer',false)->unique(fn($r)=>$r->file_number ?: $r->phone)->count()]; }

    private function customerOverview(array $filters): array
    {
        // جنسیت و جدید/قدیم برای مشتریانی است که در همین بازه مراجعه کرده‌اند (آمد، مشاوره یا انجام کار)،
        // نه کل پرونده‌ها یا تعداد نوبت‌ها. جدید بودن از علامت «مشتری جدید» خود نوبت خوانده می‌شود.
        $genderOf = fn ($value) => match (trim((string) $value)) {
            'خانم', 'زن', 'female', 'Female' => 'female',
            'آقا', 'مرد', 'male', 'Male' => 'male',
            default => 'unknown',
        };
        $patientGender = [];
        foreach (Patient::query()->whereNotNull('gender')->get(['file_number', 'phone', 'gender']) as $patient) {
            if (trim((string) $patient->file_number) !== '') $patientGender['file:'.trim((string) $patient->file_number)] = $patient->gender;
            if ($this->campaignPhone($patient->phone) !== '') $patientGender['phone:'.$this->campaignPhone($patient->phone)] = $patient->gender;
        }
        $visitors = [];
        $statuses = [];
        foreach ($this->appointments($filters)->addSelect('gender')->lazyById(500) as $appointment) {
            $status = trim((string) $appointment->status) ?: 'بدون وضعیت';
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $patientKey = $this->reportPatientKey($appointment);
            $visited = $this->isCompleted($appointment) || trim((string) $appointment->done) === 'مشاوره' || $status === 'آمد';
            if ($patientKey === '' || ! $visited) continue;
            $visitors[$patientKey] ??= ['new' => false, 'gender' => 'unknown'];
            $visitors[$patientKey]['new'] = $visitors[$patientKey]['new'] || (bool) $appointment->new_customer;
            $gender = $genderOf($appointment->gender ?: ($patientGender[$patientKey] ?? null));
            if ($gender !== 'unknown') $visitors[$patientKey]['gender'] = $gender;
        }
        arsort($statuses);

        $new = count(array_filter($visitors, fn (array $visitor) => $visitor['new']));
        $old = count($visitors) - $new;
        $genders = array_count_values(array_column($visitors, 'gender'));

        return [
            'gender' => [
                'female' => (int) ($genders['female'] ?? 0),
                'male' => (int) ($genders['male'] ?? 0),
                'unknown' => (int) ($genders['unknown'] ?? 0),
            ],
            'customers' => ['new' => $new, 'old' => $old, 'total' => $new + $old],
            'statuses' => collect($statuses)->map(fn (int $count, string $name) => ['name'=>$name, 'count'=>$count])->values()->all(),
        ];
    }
    /** answered_on میلادی ذخیره می‌شود؛ مثل گزارش رضایت‌مندی، تاریخ پاسخ به شمسی تبدیل و با بازه مقایسه می‌شود. */
    private function satisfaction(array $filters): Collection
    {
        return SatisfactionAnswer::query()->with('response:id,created_at,answered_on')->whereNotNull('score')->get()
            ->filter(function ($answer) use ($filters) {
                $date = $answer->response?->answered_on ?: $answer->response?->created_at;
                if (! $date) return false;
                $jalali = $this->gregorianToJalaliDate(Carbon::parse($date));
                return $jalali >= $filters['from'] && $jalali <= $filters['to'];
            })
            ->groupBy('question_key')
            ->map(fn ($r) => ['key'=>$r->first()->question_key,'label'=>$r->first()->question_label,'count'=>$r->count(),'percent'=>round($r->avg('score')/5*100,1)])
            ->values();
    }
    private function photoQuality(array $filters): Collection
    {
        $tags = [];
        $configuredTags = AppSetting::getByKey('service_tags', []);
        if (is_string($configuredTags)) $configuredTags = json_decode($configuredTags, true) ?: [];
        foreach ((array) $configuredTags as $configuredTag) {
            $name = trim((string) (is_array($configuredTag) ? ($configuredTag['name'] ?? '') : $configuredTag));
            if ($name !== '') $tags[$name] = ['tag'=>$name,'total'=>0,'qualified'=>0,'percent'=>0];
        }
        PatientMedia::query()
            ->where('media_type', 'image')
            ->whereNotNull('services')
            ->select(['id','services','is_featured','created_at'])
            ->lazyById(500)
            ->each(function (PatientMedia $media) use (&$tags, $filters): void {
                // فقط عکس‌هایی که در بازهٔ گزارش ثبت شده‌اند.
                if (! $media->created_at) return;
                $date = $this->gregorianToJalaliDate($media->created_at);
                if ($date < $filters['from'] || $date > $filters['to']) return;
                $mediaTags = collect($media->services ?: [])->map(function ($tag): string {
                    if (is_string($tag)) return trim($tag);
                    if (! is_array($tag)) return '';
                    return trim((string) ($tag['name'] ?? $tag['service'] ?? $tag['title'] ?? ''));
                })->filter()->unique();

                foreach ($mediaTags as $tag) {
                    $tags[$tag] ??= ['tag'=>$tag,'total'=>0,'qualified'=>0,'percent'=>0];
                    $tags[$tag]['total']++;
                    if ($media->is_featured) $tags[$tag]['qualified']++;
                }
            });

        return collect($tags)->map(function (array $row): array {
            $row['percent'] = $row['total'] > 0 ? round(($row['qualified'] / $row['total']) * 100, 1) : 0;
            return $row;
        })->sortByDesc('percent')->values();
    }
    private function services($completed, array $materialPrices): Collection
    {
        $out = [];
        foreach ($completed as $row) {
            $lines = collect($row->services ?: [])->filter(fn ($service) => is_array($service))->values();
            $grossTotal = $lines->sum(fn ($service) => $this->serviceGross($service, $materialPrices));
            $value = $this->appointmentValue($row);
            foreach ($lines as $service) {
                $name = $service['name'] ?? $service['service'] ?? 'خدمت';
                $out[$name] ??= ['name' => $name, 'revenue' => 0, 'profit' => 0];
                // سهم هر خدمت از مبلغ نهایی نوبت (بعد از تخفیف) به نسبت قیمت آن.
                $revenue = $grossTotal > 0 ? $value * $this->serviceGross($service, $materialPrices) / $grossTotal : $value / max(1, $lines->count());
                $out[$name]['revenue'] += $revenue;
                $out[$name]['profit'] += $revenue - $this->serviceMaterialCost($service, $materialPrices);
            }
        }

        return collect($out)->map(fn ($r) => [...$r, 'revenue' => round($r['revenue']), 'profit' => round($r['profit'])])->sortByDesc('revenue')->values();
    }

    private const UNASSIGNED_ADVERTISING = 'تبلیغات بدون کمپین';

    /** هزینه‌های واقعی تبلیغات در بازه (هر دسته‌ای که «تبلیغ» یا advertising دارد). */
    private function advertisingExpenses(array $filters): Builder
    {
        return Expense::query()
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$filters['from'], $filters['to']])
            ->where(fn (Builder $query) => $query->where('category', 'like', '%تبلیغ%')->orWhereRaw('LOWER(TRIM(category)) = ?', ['advertising']));
    }

    /** کانال تبلیغاتی هر کمپین (مثلاً اینستاگرام) با کلید شناسهٔ کمپین. */
    private function campaignChannels(): Collection
    {
        return Campaign::query()
            ->where(fn (Builder $query) => $query->whereNull('note')->orWhere('note', '!=', '__system_general_followups__'))
            ->get(['id', 'ui_payload'])
            ->mapWithKeys(function (Campaign $campaign): array {
                $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                return [(int) $campaign->id => trim((string) ($payload['source'] ?? $payload['sourceName'] ?? '')) ?: 'بدون کانال'];
            });
    }

    /** مبلغ فروش یک خدمت: قیمت انبار × تعداد، منهای تخفیف یا به‌اضافهٔ مازاد. */
    private function serviceNet(array $service, array $materialPrices): float
    {
        $gross = $this->serviceGross($service, $materialPrices);
        $adjustment = max(0, $this->number($service['discount'] ?? 0));

        return ($service['adjustment_mode'] ?? '') === 'surcharge' ? $gross + $adjustment : max(0, $gross - min($adjustment, $gross));
    }

    /** قیمت فروش و هزینهٔ مواد خدمات و جانبی‌ها از انبار؛ همان مبنایی که حقوق با آن حساب می‌شود. */
    private function materialPrices(): array
    {
        return [
            'inventory' => Inventory::query()->get(['id', 'name', 'amount', 'price'])->keyBy('name'),
            'addons' => InventoryAddonDefinition::query()->get(['id', 'amount', 'price'])->keyBy('id'),
        ];
    }

    private function serviceItem(array $service, array $materialPrices): ?object
    {
        if (! empty($service['addon_definition_id'])) {
            return $materialPrices['addons']->get((int) $service['addon_definition_id']);
        }

        return $materialPrices['inventory']->get(trim((string) ($service['name'] ?? '')));
    }

    private function serviceQuantity(array $service): float
    {
        // تعداد خالی صفر است، مثل مبلغ نوبت؛ فقط داده‌ای که اصلاً cc ندارد از quantity (یا یک) می‌خواند.
        return max(0, $this->number(array_key_exists('cc', $service) ? ($service['cc'] ?? 0) : ($service['quantity'] ?? 1)));
    }

    private function serviceGross(array $service, array $materialPrices): float
    {
        $item = $this->serviceItem($service, $materialPrices);
        $unit = $item ? $this->number($item->amount) : $this->number($service['price'] ?? $service['amount'] ?? 0);

        return $unit * $this->serviceQuantity($service);
    }

    /** هزینهٔ مواد یک خدمت و جانبی‌هایش؛ اگر خدمت در انبار نباشد، مقدار ذخیره‌شده در نوبت. */
    private function serviceMaterialCost(array $service, array $materialPrices): float
    {
        $item = $this->serviceItem($service, $materialPrices);
        $cost = $item ? $this->number($item->price) * $this->serviceQuantity($service) : $this->number($service['material_cost'] ?? 0);

        return $cost + collect($service['addons'] ?? [])
            ->filter(fn ($addon) => is_array($addon))
            ->sum(fn ($addon) => $this->serviceMaterialCost($addon, $materialPrices));
    }

    private function appointmentMaterialCost($row, array $materialPrices): float
    {
        return collect($row->services ?: [])
            ->filter(fn ($service) => is_array($service))
            ->sum(fn ($service) => $this->serviceMaterialCost($service, $materialPrices));
    }

    private function salesTimeline($completed): Collection { $out=[]; foreach($completed as $row){$date=$this->dateOf($row);$out[$date]??=['date'=>$date,'revenue'=>0,'count'=>0];$out[$date]['revenue']+=$this->appointmentValue($row);$out[$date]['count']++;} ksort($out); return collect(array_values($out))->map(fn($row)=>[...$row,'revenue'=>round($row['revenue'])]); }
    private function birthdays(array $filters): Collection { $month=(int)substr($filters['to'],5,2); return Patient::query()->whereNotNull('birth_date')->get()->filter(fn($p)=>preg_match('/(?:-|\/)(\d{1,2})(?:-|\/|$)/',(string)$p->birth_date,$m)&&(int)$m[1]===$month)->map(fn($p)=>['id'=>$p->id,'name'=>trim($p->first_name.' '.$p->last_name),'birth_date'=>$p->birth_date])->values(); }
    private function capacity(float $revenue): array { $limit=max(0,(float)AppSetting::getByKey('report_monthly_capacity',0)); return ['limit'=>round($limit),'revenue'=>round($revenue),'percent'=>$limit?round(min(100,$revenue/$limit*100),1):0]; }
    private function isCompleted($row):bool{return trim((string)$row->done)==='انجام شد';}
    private function recordedPaymentAmount($row):float{$details=is_array($row->payment_details??null)?$row->payment_details:[];if(array_key_exists('ledger_total',$details))return max(0,$this->number($details['ledger_total']));return max(0,$this->number($details['cash']??0)+$this->number($details['card']??0)+$this->number(data_get($details,'check.amount',0)));}
    /** درآمد با انجام خدمت شناسایی می‌شود، حتی اگر بیمار هنوز بدهکار باشد. */
    /** جمع پرداخت‌ها و تسویه‌های بدهی ثبت‌شده در دفتر مالی هر نوبت (شامل پرداخت از کیف پول). */
    private function ledgerReceipts($appointmentIds): Collection
    {
        $ids = collect($appointmentIds)->filter()->values();
        if ($ids->isEmpty()) return collect();

        // union شناسهٔ نوبت را به‌عنوان کلید نگه می‌دارد (flatMap کلیدها را از نو شماره می‌زند).
        return $ids->chunk(1000)->reduce(fn (Collection $carry, $chunk) => $carry->union(AppointmentFinancialTransaction::query()
            ->whereIn('appointment_id', $chunk)
            ->whereIn('type', ['payment', 'debt_settlement'])
            ->whereNull('voided_at')
            ->selectRaw('appointment_id, SUM(amount) AS total')
            ->groupBy('appointment_id')
            ->pluck('total', 'appointment_id')
            ->map(fn ($total) => (float) $total)), collect());
    }

    /**
     * پولی که برای یک نوبت واقعاً دریافت شده: پرداخت‌ها و تسویهٔ بعدی بدهی (نقد، کارت، چک یا کیف پول)
     * به‌اضافهٔ کیف پولی که روی خود ردیف نوبت اعمال شده است.
     */
    private function receivedAmount($row, Collection $ledgerReceipts): float
    {
        $wallet = max(0, $this->number($row->wallet_applied ?? 0));
        if ($ledgerReceipts->has($row->id)) return $ledgerReceipts->get($row->id) + $wallet;

        $details = is_array($row->payment_details ?? null) ? $row->payment_details : [];
        $debtPayments = collect($details['debt_payments'] ?? [])->sum(fn ($payment) => is_array($payment) ? $this->number($payment['amount'] ?? 0) : 0);

        return $this->recordedPaymentAmount($row) + $debtPayments + $wallet;
    }

    private function isRevenueRecognized($row): bool
    {
        return $this->isCompleted($row);
    }
    private function isCancelled($row):bool{$s=(string)$row->status;return str_contains($s,'کنسل')||str_contains($s,'لغو');} private function dateOf($row):string{return ($row->month?:'').'-'.str_pad((string)($row->day_num?:1),2,'0',STR_PAD_LEFT);} private function appointmentValue($row):float{
        // مبلغ نوبت پس از تخفیف است؛ کیف پول روش پرداخت است و به درآمد برمی‌گردد.
        $value=$this->number($row->amount)+max(0,$this->number($row->wallet_applied??0));
        if($value>0)return $value;
        $gross=collect($row->services?:[])->sum(fn($s)=>is_array($s)?$this->number($s['price']??$s['amount']??0)*max(1,$this->number($s['quantity']??$s['cc']??1)):0);
        return max(0,$gross-$this->number($row->discount??0));
    } private function number($value):float{return (float)str_replace([',','٬',' '],'',(string)$value);} private function normalizeDate($value):?string{$v=strtr(trim((string)$value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',$v)?$v:null;} private function monthsBetween($from,$to):array{$out=[];[$y,$m]=array_map('intval',explode('-',substr($from,0,7)));$end=substr($to,0,7);while(sprintf('%04d-%02d',$y,$m)<=$end){$out[]=sprintf('%04d-%02d',$y,$m);if(++$m===13){$m=1;$y++;}}return $out;} private function salaryRatio(array $filters):float { $months=$this->monthsBetween($filters['from'],$filters['to']); if(count($months)===1)return max(0,((int)substr($filters['to'],8,2)-(int)substr($filters['from'],8,2)+1)/30); return max(0, count($months)-2 + (31-(int)substr($filters['from'],8,2)+1)/30 + (int)substr($filters['to'],8,2)/30); }
}
