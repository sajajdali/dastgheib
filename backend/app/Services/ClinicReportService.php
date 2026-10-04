<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Expense;
use App\Models\Inventory;
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
        $paidCompleted = $completed->filter(fn ($row) => $this->isPaidCompleted($row));
        $statuses = $this->statusCounts($appointments);
        $revenue = $paidCompleted->sum(fn ($row) => $this->appointmentValue($row));
        $cash = $revenue;
        $discountTotal = $paidCompleted->sum(fn ($row) => $this->number($row->discount));
        $visitorCount = $completed->count();
        $materialsTotal = $paidCompleted->sum(function ($row) {
            return collect($row->services ?: [])->sum(fn ($service) => $this->number($service['material_cost'] ?? 0));
        });
        if ($progress) $progress(22, 'محاسبه درآمد، تخفیف و مراجعین');
        $expenseQuery = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense');
        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        $expenseItems = (clone $expenseQuery)->selectRaw("COALESCE(NULLIF(category, ''), 'بدون دسته‌بندی') AS category, SUM(amount) AS amount")->groupBy('category')->get();
        $advertisingTotal = (float) (clone $expenseQuery)->whereIn(DB::raw('LOWER(TRIM(category))'), ['advertising','تبلیغات'])->sum('amount');
        $campaignExpenses = (clone $expenseQuery)->with('campaign:id,name,ui_payload')
            ->whereIn(DB::raw('LOWER(TRIM(category))'), ['advertising','تبلیغات'])
            ->whereNotNull('campaign_id')->selectRaw('campaign_id, SUM(amount) AS amount')->groupBy('campaign_id')->get();
        if ($progress) $progress(34, 'جمع‌بندی هزینه‌ها و تبلیغات');
        $resourceCosts = $this->resourceCosts($filters, true);
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
        $services = $this->services($paidCompleted);
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
                'expenses' => round($expenseTotal),
                'doctor_cost' => round($resourceCosts['doctor_total']),
                'staff_cost' => round($resourceCosts['staff_total']),
                'materials_cost' => round($materialsTotal),
                'discount_total' => round($discountTotal),
                'visitors_count' => $visitorCount,
                'net_profit' => round($revenue - $expenseTotal - $resourceCosts['doctor_total'] - $resourceCosts['staff_total'] - $materialsTotal),
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

        Campaign::query()
            ->where(fn (Builder $query) => $query->whereNull('note')->orWhere('note', '!=', '__system_general_followups__'))
            ->whereBetween('starts_on', [$filters['from'], $filters['to']])
            ->get(['id','name','budget','ui_payload'])
            ->each(function (Campaign $campaign) use (&$rows): void {
                $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
                $name = trim((string) ($payload['source'] ?? '')) ?: 'بدون کانال';
                $key = mb_strtolower($name);
                $rows[$key] ??= ['name'=>$name,'completed'=>0,'revenue'=>0.0,'cost'=>0.0];
                $rows[$key]['cost'] += (float) $campaign->budget;
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
        $inventoryPrices = Inventory::query()->get(['name','price'])->mapWithKeys(fn (Inventory $item) => [
            $this->reportNameKey($item->name) => $this->number($item->price),
        ]);

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

            if ($this->isPaidCompleted($appointment)) {
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
            if (!$this->isPaidCompleted($appointment)) continue;
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
            if (!$this->isPaidCompleted($appointment)) continue;
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

    private function appointmentDoctorRevenueShares($appointment, Collection $inventoryPrices): array
    {
        $fallbackDoctors = collect(preg_split('/[،,]+/u', (string) $appointment->doctor))->map(fn ($name) => trim($name))->filter()->unique()->values();
        $weights = [];
        foreach (collect($appointment->services ?: []) as $service) {
            $doctor = trim((string) data_get($service, 'doctor'));
            if ($doctor === '' && $fallbackDoctors->count() === 1) $doctor = (string) $fallbackDoctors->first();
            if ($doctor === '') continue;
            $quantity = max(1, $this->number(data_get($service, 'quantity', data_get($service, 'cc', 1))));
            $price = $this->number(data_get($service, 'price', data_get($service, 'amount', 0)));
            if ($price <= 0) $price = (float) $inventoryPrices->get($this->reportNameKey(data_get($service, 'name')), 0);
            $weight = $price * $quantity;
            foreach (collect(data_get($service, 'addons', [])) as $addon) {
                $addonQuantity = max(1, $this->number(data_get($addon, 'quantity', data_get($addon, 'cc', 1))));
                $addonPrice = $this->number(data_get($addon, 'price', data_get($addon, 'amount', 0)));
                if ($addonPrice <= 0) $addonPrice = (float) $inventoryPrices->get($this->reportNameKey(data_get($addon, 'name')), 0);
                $weight += $addonPrice * $addonQuantity;
            }
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
                if (! $this->isPaidCompleted($appointment)) return;
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
                if (! $this->isPaidCompleted($appointment)) continue;
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
            $rows = $this->appointments($filters)->get()->filter(fn ($row) => $metric === 'forecast_revenue' ? str_contains((string) $row->status, 'وقت') : $this->isPaidCompleted($row));
            return ['metric'=>$metric, 'rows'=>$rows->map(fn ($row) => ['id'=>$row->id,'date'=>$this->dateOf($row),'patient'=>trim(($row->firstname ?? '').' '.($row->lastname ?? '')),'status'=>$row->status,'done'=>$row->done,'amount'=>round($this->appointmentValue($row))])->values(), 'total'=>round($rows->sum(fn ($row) => $this->appointmentValue($row)))];
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
            ->filter(fn ($appointment) => $this->isPaidCompleted($appointment));

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
                    $quantity = max(1, $this->number($line['cc'] ?? $line['quantity'] ?? 1));
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
                        : $this->number($line['material_cost'] ?? $inventory?->price ?? 0) * $quantity;
                    $commission = (float) $matched->sum('amount');

                    $calculatedLines[] = compact('name', 'quantity', 'revenue', 'materialCost', 'commission');
                }
            }

            // Appointment amount is already net of service discounts. Wallet is
            // a payment method, so add it back; debt never changes recognized revenue.
            $rawRevenue = collect($calculatedLines)->sum('revenue');
            $savedAppointmentRevenue = $this->number($appointment->amount) + $this->number($appointment->wallet_applied ?? 0);
            $targetRevenue = $savedAppointmentRevenue > 0 ? $savedAppointmentRevenue : $rawRevenue;
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
            'totals' => [
                'revenue' => (int) $result->sum('revenue'),
                'material_cost' => (int) $result->sum('material_cost'),
                'commission' => (int) $result->sum('commission'),
                'profit' => (int) $result->sum('profit'),
            ],
        ];
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

    private function resourceCosts(array $filters, bool $paidAppointmentsOnly = false): array
    {
        $months = $this->monthsBetween($filters['from'], $filters['to']);
        $paidAppointmentIds = $paidAppointmentsOnly
            ? $this->appointments($filters)->get()->filter(fn ($appointment) => $this->isPaidCompleted($appointment))->pluck('id')
            : collect();
        $lines = ResourceEarningLine::query()
            ->whereIn('month', $months)
            ->where('status', 'active')
            ->when($paidAppointmentsOnly, fn (Builder $query) => $query->where(
                fn (Builder $nested) => $nested->whereNull('appointment_id')->orWhereIn('appointment_id', $paidAppointmentIds)
            ))
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
            $commission = (float) $items->whereIn('earning_type', ['base_commission','inventory_commission','sales_bonus'])->sum('amount');
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
            $name = trim((string) ($payload['source'] ?? $payload['sourceName'] ?? '')) ?: 'بدون کانال';
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
        $costs = Expense::query()
            ->whereBetween('occurred_on', [$filters['from'], $filters['to']])
            ->where('type', 'expense')
            ->whereIn(DB::raw('LOWER(TRIM(category))'), ['advertising', 'تبلیغات'])
            ->selectRaw("SUBSTRING(occurred_on, 1, 7) AS report_month, SUM(amount) AS total")
            ->groupBy('report_month')
            ->pluck('total', 'report_month');
        $revenues = [];
        $this->appointments($filters)->whereNotNull('campaign_id')->lazyById(500)->each(function ($appointment) use (&$revenues) {
            if (! $this->isCompleted($appointment)) return;
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
        $campaigns = Campaign::query()
            ->where(fn (Builder $query) => $query->whereNull('note')->orWhere('note', '!=', '__system_general_followups__'))
            ->get(['id', 'name', 'budget', 'ui_payload'])
            ->map(function (Campaign $campaign) use ($filters): array {
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
            $cost = (float) $campaign->budget;
            $appointments = $bookedPhones->count();
            $leadCount = $uniqueLeads->count();

            return [
                'campaign_id' => (int) $campaign->id,
                'name' => $campaign->name ?: 'کمپین',
                'advertising_cost' => round($cost),
                'appointments' => $appointments,
                'cost_per_appointment' => $appointments > 0 ? round($cost / $appointments) : null,
                'leads' => $leadCount,
                'acquisition_rate' => $leadCount > 0 ? round(($appointments / $leadCount) * 100, 1) : null,
            ];
        // The selected range is based on contact rows. A campaign with no
        // uniquely identifiable lead in the range must not contribute its
        // full creation cost to that range.
        })->filter(fn (array $row) => $row['leads'] > 0)->values();
        $totalCost = (float) $campaigns->sum('advertising_cost');
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
            'silver' => ['key' => 'silver', 'label' => 'معمولی', 'count' => 0, 'revenue' => 0],
            'blue' => ['key' => 'blue', 'label' => 'خوب', 'count' => 0, 'revenue' => 0],
            'gold' => ['key' => 'gold', 'label' => 'CIP', 'count' => 0, 'revenue' => 0],
            'problematic' => ['key' => 'problematic', 'label' => 'مشکل‌ساز', 'count' => 0, 'revenue' => 0],
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
            if ($this->isPaidCompleted($appointment)) $customers[$key] += $this->appointmentValue($appointment);
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
        Patient::query()->select(['id', 'file_number', 'phone', 'customer_level'])
            ->chunkById(100, function (Collection $patients) use (&$levels, $customers, $progress, $patientTotal, &$processedPatients) {
                $matching = $patients->filter(fn (Patient $patient) =>
                    ($patient->file_number && array_key_exists('f:'.$patient->file_number, $customers))
                    || ($patient->phone && array_key_exists('p:'.$patient->phone, $customers))
                )->values();
                $matching->chunk(25)->each(fn (Collection $chunk) => $this->customerLevels->decorate($chunk));
                foreach ($matching as $patient) {
                    $key = ($patient->file_number && array_key_exists('f:'.$patient->file_number, $customers))
                        ? 'f:'.$patient->file_number
                        : 'p:'.$patient->phone;
                    $level = in_array($patient->customer_level, ['silver', 'blue', 'gold', 'problematic'], true)
                        ? $patient->customer_level
                        : 'silver';
                    $row = $levels[$level];
                    $row['count']++;
                    $row['revenue'] += $customers[$key];
                    $levels[$level] = $row;
                }
                $processedPatients += $patients->count();
                if ($progress && ($processedPatients % 500 === 0 || $processedPatients >= $patientTotal)) {
                    $percent = $patientTotal ? 40 + (int) floor(($processedPatients / $patientTotal) * 55) : 95;
                    $progress(min(95, $percent), "درجه‌بندی {$processedPatients} از {$patientTotal} مشتری");
                }
            });

        return $levels->map(function (array $row) {
            $row['revenue'] = round($row['revenue']);
            return $row;
        })->values();
    }
    private function loyalty($appointments): array
    {
        $currentKeys = $appointments
            ->filter(fn ($row) => $this->isPaidCompleted($row))
            ->map(fn ($row) => $row->file_number ?: $row->phone)
            ->filter()->unique()->values();
        $customerKey = "COALESCE(NULLIF(file_number, ''), phone)";
        $paidHistory = $currentKeys->isEmpty() ? collect() : Appointment::query()
            ->where('done', 'انجام شد')
            ->whereIn(DB::raw($customerKey), $currentKeys->all())
            ->get(['id','file_number','phone','done','amount','wallet_applied','debt','services','payment_details'])
            ->filter(fn ($appointment) => $this->isPaidCompleted($appointment));
        $loyal = $paidHistory
            ->groupBy(fn ($appointment) => $appointment->file_number ?: $appointment->phone)
            ->filter(fn ($visits) => $visits->count() >= 2)
            ->count();
        $allCompletedCustomers = Appointment::query()
            ->where('done', 'انجام شد')
            ->whereNotNull(DB::raw($customerKey))
            ->get(['id','file_number','phone','done','amount','wallet_applied','debt','services','payment_details'])
            ->filter(fn ($appointment) => $this->isPaidCompleted($appointment))
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
        $genderCounts = Patient::query()
            ->selectRaw("CASE WHEN TRIM(gender) IN ('خانم','زن','female','Female') THEN 'female' WHEN TRIM(gender) IN ('آقا','مرد','male','Male') THEN 'male' ELSE 'unknown' END AS gender_group, COUNT(*) AS aggregate_count")
            ->groupBy('gender_group')
            ->pluck('aggregate_count', 'gender_group');

        $appointmentCounts = [];
        $statuses = [];
        foreach ($this->appointments($filters)->lazyById(500) as $appointment) {
            $patientKey = $this->reportPatientKey($appointment);
            if ($patientKey !== '') $appointmentCounts[$patientKey] = ($appointmentCounts[$patientKey] ?? 0) + 1;
            $status = trim((string) $appointment->status) ?: 'بدون وضعیت';
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }
        arsort($statuses);

        $new = count(array_filter($appointmentCounts, fn (int $count) => $count === 1));
        $old = count(array_filter($appointmentCounts, fn (int $count) => $count > 1));

        return [
            'gender' => [
                'female' => (int) ($genderCounts['female'] ?? 0),
                'male' => (int) ($genderCounts['male'] ?? 0),
                'unknown' => (int) ($genderCounts['unknown'] ?? 0),
            ],
            'customers' => ['new' => $new, 'old' => $old, 'total' => $new + $old],
            'statuses' => collect($statuses)->map(fn (int $count, string $name) => ['name'=>$name, 'count'=>$count])->values()->all(),
        ];
    }
    private function satisfaction(array $filters): Collection { return SatisfactionAnswer::query()->whereHas('response',fn($q)=>$q->whereBetween('answered_on',[$filters['from'],$filters['to']]))->get()->groupBy('question_key')->map(fn($r)=>['key'=>$r->first()->question_key,'label'=>$r->first()->question_label,'count'=>$r->count(),'percent'=>round($r->avg('score')/5*100,1)])->values(); }
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
            ->select(['id','services','is_featured'])
            ->lazyById(500)
            ->each(function (PatientMedia $media) use (&$tags): void {
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
    private function services($completed): Collection { $out=[]; foreach($completed as $r){foreach(($r->services?:[]) as $s){$name=$s['name']??$s['service']??'خدمت';$out[$name]??=['name'=>$name,'revenue'=>0,'profit'=>0];$revenue=$this->number($s['price']??$s['amount']??0);$out[$name]['revenue']+=$revenue;$out[$name]['profit']+=$revenue-$this->number($s['material_cost']??0);}} return collect($out)->map(fn($r)=>[...$r,'revenue'=>round($r['revenue']),'profit'=>round($r['profit'])])->sortByDesc('revenue')->values(); }
    private function salesTimeline($completed): Collection { $out=[]; foreach($completed as $row){$date=$this->dateOf($row);$out[$date]??=['date'=>$date,'revenue'=>0,'count'=>0];$out[$date]['revenue']+=$this->appointmentValue($row);$out[$date]['count']++;} ksort($out); return collect(array_values($out))->map(fn($row)=>[...$row,'revenue'=>round($row['revenue'])]); }
    private function birthdays(array $filters): Collection { $month=(int)substr($filters['to'],5,2); return Patient::query()->whereNotNull('birth_date')->get()->filter(fn($p)=>preg_match('/(?:-|\/)(\d{1,2})(?:-|\/|$)/',(string)$p->birth_date,$m)&&(int)$m[1]===$month)->map(fn($p)=>['id'=>$p->id,'name'=>trim($p->first_name.' '.$p->last_name),'birth_date'=>$p->birth_date])->values(); }
    private function capacity(float $revenue): array { $limit=max(0,(float)AppSetting::getByKey('report_monthly_capacity',0)); return ['limit'=>round($limit),'revenue'=>round($revenue),'percent'=>$limit?round(min(100,$revenue/$limit*100),1):0]; }
    private function isCompleted($row):bool{return trim((string)$row->done)==='انجام شد';}
    private function recordedPaymentAmount($row):float{$details=is_array($row->payment_details??null)?$row->payment_details:[];if(array_key_exists('ledger_total',$details))return max(0,$this->number($details['ledger_total']));return max(0,$this->number($details['cash']??0)+$this->number($details['card']??0)+$this->number(data_get($details,'check.amount',0)));}
    private function isPaidCompleted($row):bool{if(!$this->isCompleted($row)||$this->number($row->debt??0)>0)return false;$payable=max(0,$this->number($row->amount??0));$wallet=max(0,$this->number($row->wallet_applied??0));$paid=$this->recordedPaymentAmount($row);return $payable>0?$paid>=$payable:($paid+$wallet)>0;}
    private function isCancelled($row):bool{$s=(string)$row->status;return str_contains($s,'کنسل')||str_contains($s,'لغو');} private function dateOf($row):string{return ($row->month?:'').'-'.str_pad((string)($row->day_num?:1),2,'0',STR_PAD_LEFT);} private function appointmentValue($row):float{$services=collect($row->services?:[]);$sum=$services->sum(fn($s)=>$this->number($s['price']??$s['amount']??0)*max(1,$this->number($s['quantity']??1)));return $sum?:$this->number($row->amount);} private function number($value):float{return (float)str_replace([',','٬',' '],'',(string)$value);} private function normalizeDate($value):?string{$v=strtr(trim((string)$value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',$v)?$v:null;} private function monthsBetween($from,$to):array{$out=[];[$y,$m]=array_map('intval',explode('-',substr($from,0,7)));$end=substr($to,0,7);while(sprintf('%04d-%02d',$y,$m)<=$end){$out[]=sprintf('%04d-%02d',$y,$m);if(++$m===13){$m=1;$y++;}}return $out;} private function salaryRatio(array $filters):float { $months=$this->monthsBetween($filters['from'],$filters['to']); if(count($months)===1)return max(0,((int)substr($filters['to'],8,2)-(int)substr($filters['from'],8,2)+1)/30); return max(0, count($months)-2 + (31-(int)substr($filters['from'],8,2)+1)/30 + (int)substr($filters['to'],8,2)/30); }
}
