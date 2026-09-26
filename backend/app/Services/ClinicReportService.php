<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\PhotoQualityReview;
use App\Models\ResourceAdjustment;
use App\Models\ResourceEarningLine;
use App\Models\SatisfactionAnswer;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Staff;
use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClinicReportService
{
    public function __construct(private readonly CustomerLevelService $customerLevels) {}

    public function dashboard(array $filters): array
    {
        // Read appointments in bounded batches. The lazy collection can be
        // iterated repeatedly, but never hydrates the whole report range.
        $appointments = $this->appointments($filters)->lazyById(500);
        $completed = $appointments->filter(fn ($row) => $this->isCompleted($row));
        $statuses = $this->statusCounts($appointments);
        $revenue = $completed->sum(fn ($row) => $this->appointmentValue($row));
        $cash = $completed->sum(fn ($row) => max(0, $this->appointmentValue($row) - $this->number($row->debt)));
        $expenseQuery = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense');
        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        $expenseItems = (clone $expenseQuery)->selectRaw("COALESCE(NULLIF(category, ''), 'بدون دسته‌بندی') AS category, SUM(amount) AS amount")->groupBy('category')->get();
        $advertisingTotal = (float) (clone $expenseQuery)->whereIn(DB::raw('LOWER(TRIM(category))'), ['advertising','تبلیغات'])->sum('amount');
        $campaignExpenses = (clone $expenseQuery)->with('campaign:id,name')->whereNotNull('campaign_id')->selectRaw('campaign_id, SUM(amount) AS amount')->groupBy('campaign_id')->get();
        $resourceCosts = $this->resourceCosts($filters);
        $forecast = $this->forecast($filters, $appointments);

        return [
            'filters' => $filters,
            'kpis' => [
                'recognized_revenue' => round($revenue),
                'cash_collected' => round($cash),
                'expenses' => round($expenseTotal),
                'doctor_cost' => round($resourceCosts['doctor_total']),
                'staff_cost' => round($resourceCosts['staff_total']),
                'net_profit' => round($revenue - $expenseTotal - $resourceCosts['doctor_total'] - $resourceCosts['staff_total']),
                'forecast_revenue' => round($forecast['value']),
            ],
            'sales_timeline' => $this->salesTimeline($completed),
            'cancellation' => $statuses,
            'forecast' => $forecast,
            'expenses' => ['total'=>round($expenseTotal),'advertising_total'=>round($advertisingTotal),'items'=>$expenseItems->map(fn($row)=>['category'=>$row->category,'amount'=>round($row->amount)])->values()],
            'resources' => $resourceCosts['resources'],
            'campaigns' => $this->campaigns($appointments, $campaignExpenses),
            'channels' => $this->channels($completed, $campaignExpenses),
            'advertising_roi' => $this->advertisingRoi($filters),
            'customer_segments' => $this->customerSegments($appointments),
            'loyalty' => $this->loyalty($appointments),
            'customers' => $this->customers($filters),
            'satisfaction' => $this->satisfaction($filters),
            'photo_quality' => $this->photoQuality($filters),
            'services' => $this->services($completed),
            'birthdays' => $this->birthdays($filters),
            'capacity' => $this->capacity($revenue),
        ];
    }

    public function normalizeFilters(array $input): array
    {
        $from = $this->normalizeDate($input['from'] ?? null) ?? '1400-01-01';
        $to = $this->normalizeDate($input['to'] ?? null) ?? '1499-12-29';
        abort_if($from > $to, 422, 'بازه گزارش معتبر نیست.');
        return ['from'=>$from,'to'=>$to,'doctor'=>$input['doctor'] ?? null,'consultant'=>$input['consultant'] ?? null,'campaign_id'=>filled($input['campaign_id'] ?? null) ? (int)$input['campaign_id'] : null,'status'=>array_filter((array)($input['status'] ?? [])),'done'=>array_filter((array)($input['done'] ?? []))];
    }

    public function drilldown(string $metric, array $filters): array
    {
        if (in_array($metric, ['recognized_revenue', 'cash_collected', 'forecast_revenue'], true)) {
            $rows = $this->appointments($filters)->get()->filter(fn ($row) => $metric === 'forecast_revenue' ? str_contains((string) $row->status, 'وقت') : $this->isCompleted($row));
            return ['metric'=>$metric, 'rows'=>$rows->map(fn ($row) => ['id'=>$row->id,'date'=>$this->dateOf($row),'patient'=>trim(($row->firstname ?? '').' '.($row->lastname ?? '')),'status'=>$row->status,'done'=>$row->done,'amount'=>round($this->appointmentValue($row))])->values(), 'total'=>round($rows->sum(fn ($row) => $this->appointmentValue($row)))];
        }
        if ($metric === 'expenses') {
            $rows = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense')->get();
            return ['metric'=>$metric,'rows'=>$rows->map(fn($row)=>['id'=>$row->id,'date'=>$row->occurred_on,'title'=>$row->title,'category'=>$row->category,'amount'=>(float)$row->amount,'campaign_id'=>$row->campaign_id])->values(),'total'=>round($rows->sum('amount'))];
        }
        if ($metric === 'cancellation') {
            $rows = $this->appointments($filters)->get()->filter(fn($row)=>str_contains((string)$row->status,'آمد') || $this->isCancelled($row) || str_contains((string)$row->status,'پاسخ نداد'));
            return ['metric'=>$metric,'rows'=>$rows->map(fn($row)=>['id'=>$row->id,'date'=>$this->dateOf($row),'patient'=>trim(($row->firstname ?? '').' '.($row->lastname ?? '')),'status'=>$row->status])->values(),'summary'=>$this->statusCounts($rows)];
        }
        abort(404, 'جزئیات این شاخص تعریف نشده است.');
    }

    private function appointments(array $filters): Builder
    {
        return Appointment::query()->select([
            'id', 'month', 'day_num', 'lastname', 'phone', 'file_number',
            'status', 'done', 'amount', 'debt', 'services', 'source', 'campaign_id',
            'new_customer', 'doctor', 'consultant', 'completed_at',
        ])->where(function(Builder $q) use($filters) {
            [$fm,$fd]=[substr($filters['from'],0,7),(int)substr($filters['from'],8,2)]; [$tm,$td]=[substr($filters['to'],0,7),(int)substr($filters['to'],8,2)];
            $q->where(fn($x)=>$x->where('month','>',$fm)->orWhere(fn($y)=>$y->where('month',$fm)->where('day_num','>=',$fd)))
              ->where(fn($x)=>$x->where('month','<',$tm)->orWhere(fn($y)=>$y->where('month',$tm)->where('day_num','<=',$td)));
        })->when($filters['doctor'],fn($q,$v)=>$q->where('doctor',$v))->when($filters['consultant'],fn($q,$v)=>$q->where('consultant',$v))->when($filters['campaign_id'],fn($q,$v)=>$q->where('campaign_id',$v))->when($filters['status'],fn($q,$v)=>$q->whereIn('status',$v))->when($filters['done'],fn($q,$v)=>$q->whereIn('done',$v));
    }

    private function statusCounts($rows): array
    {
        $arrived=$rows->filter(fn($r)=>str_contains((string)$r->status,'آمد'))->count();
        $cancelled=$rows->filter(fn($r)=>$this->isCancelled($r))->count();
        $noAnswer=$rows->filter(fn($r)=>str_contains((string)$r->status,'پاسخ نداد'))->count();
        $base=$arrived+$cancelled+$noAnswer;
        return ['arrived'=>$arrived,'cancelled'=>$cancelled,'no_answer'=>$noAnswer,'eligible'=>$base,'rate'=>$base?round((($cancelled+$noAnswer)/$base)*100,1):0];
    }

    private function forecast(array $filters, $appointments): array
    {
        $month=substr($filters['from'],0,7); [$year,$m]=array_map('intval',explode('-',$month)); $previous=sprintf('%04d-%02d',$m===1?$year-1:$year,$m===1?12:$m-1);
        $prior=Appointment::query()->where('month',$previous)->select(['id','status','amount','services'])->lazyById(500); $rate=$this->statusCounts($prior)['rate']/100;
        $scheduled=$appointments->filter(fn($r)=>str_contains((string)$r->status,'وقت'));
        $gross=$scheduled->sum(fn($r)=>$this->appointmentValue($r));
        return ['scheduled_value'=>round($gross),'previous_month_cancellation_rate'=>round($rate*100,1),'value'=>round($gross*(1-$rate))];
    }

    private function resourceCosts(array $filters): array
    {
        $months = $this->monthsBetween($filters['from'], $filters['to']);
        $lines = ResourceEarningLine::query()->whereIn('month', $months)->where('status', 'active')->get()
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

    private function campaigns($appointments, Collection $expenses): Collection { return $expenses->whereNotNull('campaign_id')->groupBy('campaign_id')->map(function($rows,$id)use($appointments){$ap=$appointments->where('campaign_id',(int)$id);$count=$ap->count();$completed=$ap->filter(fn($r)=>$this->isCompleted($r))->count();$cost=(float)$rows->sum('amount');return ['campaign_id'=>(int)$id,'name'=>$rows->first()->campaign?->name ?? 'کمپین','cost'=>round($cost),'appointments'=>$count,'cac'=>round($cost/max(1,$count)),'quality'=>$count?round($completed/$count*100,1):0];})->values(); }
    private function channels($completed, Collection $expenses): Collection
    {
        $rows = [];
        foreach ($completed as $appointment) {
            $name = $appointment->source ?: 'بدون کانال';
            $rows[$name] ??= ['name'=>$name,'completed'=>0,'revenue'=>0,'cost'=>0,'cac'=>0];
            $rows[$name]['completed']++;
            $rows[$name]['revenue'] += $this->appointmentValue($appointment);
        }
        return collect($rows)->map(fn($row) => [...$row, 'revenue'=>round($row['revenue'])])->values();
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
    private function customerSegments($appointments): Collection
    {
        $levels = collect([
            'silver' => ['key' => 'silver', 'label' => 'معمولی', 'count' => 0, 'revenue' => 0],
            'blue' => ['key' => 'blue', 'label' => 'خوب', 'count' => 0, 'revenue' => 0],
            'gold' => ['key' => 'gold', 'label' => 'CIP', 'count' => 0, 'revenue' => 0],
            'problematic' => ['key' => 'problematic', 'label' => 'مشکل‌ساز', 'count' => 0, 'revenue' => 0],
        ]);

        $customers = [];
        foreach ($appointments as $appointment) {
            $key = $appointment->file_number
                ? 'f:'.(string) $appointment->file_number
                : ($appointment->phone ? 'p:'.(string) $appointment->phone : null);
            if (! $key) continue;
            $customers[$key] ??= 0;
            if ($this->isCompleted($appointment)) $customers[$key] += $this->appointmentValue($appointment);
        }
        if ($customers === []) return $levels->values();

        // Scan patient records in small batches as well. Only matching patients
        // are decorated, and both patient models and their history are released
        // before the next batch is read.
        Patient::query()->select(['id', 'file_number', 'phone', 'customer_level'])
            ->chunkById(100, function (Collection $patients) use (&$levels, $customers) {
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
            });

        return $levels->map(function (array $row) {
            $row['revenue'] = round($row['revenue']);
            return $row;
        })->values();
    }
    private function loyalty($appointments): array
    {
        $currentKeys = $appointments
            ->filter(fn ($row) => $this->isCompleted($row))
            ->map(fn ($row) => $row->file_number ?: $row->phone)
            ->filter()->unique()->values();
        $customerKey = "COALESCE(NULLIF(file_number, ''), phone)";
        $loyal = $currentKeys->isEmpty() ? 0 : Appointment::query()
            ->where('done', 'انجام شد')
            ->whereIn(DB::raw($customerKey), $currentKeys->all())
            ->selectRaw("{$customerKey} AS customer_key, COUNT(*) AS visits")
            ->groupBy('customer_key')
            ->having('visits', '>=', 2)
            ->get()->count();
        $allCompletedCustomers = (int) Appointment::query()
            ->where('done', 'انجام شد')
            ->whereNotNull(DB::raw($customerKey))
            ->distinct()
            ->count(DB::raw($customerKey));

        return [
            'completed_customers' => $currentKeys->count(),
            'loyal' => $loyal,
            'churned' => max(0, $allCompletedCustomers - $currentKeys->count()),
        ];
    }
    private function customers(array $filters): array { $rows=$this->appointments($filters)->lazyById(500); return ['new'=>$rows->where('new_customer',true)->unique(fn($r)=>$r->file_number ?: $r->phone)->count(),'old'=>$rows->where('new_customer',false)->unique(fn($r)=>$r->file_number ?: $r->phone)->count()]; }
    private function satisfaction(array $filters): Collection { return SatisfactionAnswer::query()->whereHas('response',fn($q)=>$q->whereBetween('answered_on',[$filters['from'],$filters['to']]))->get()->groupBy('question_key')->map(fn($r)=>['key'=>$r->first()->question_key,'label'=>$r->first()->question_label,'count'=>$r->count(),'percent'=>round($r->avg('score')/5*100,1)])->values(); }
    private function photoQuality(array $filters): Collection { return PhotoQualityReview::query()->whereBetween('reviewed_on',[$filters['from'],$filters['to']])->get()->groupBy('service_tag')->map(fn($r,$tag)=>['tag'=>$tag,'total'=>$r->count(),'qualified'=>$r->where('is_qualified',true)->count(),'percent'=>round($r->where('is_qualified',true)->count()/max(1,$r->count())*100,1)])->values(); }
    private function services($completed): Collection { $out=[]; foreach($completed as $r){foreach(($r->services?:[]) as $s){$name=$s['name']??$s['service']??'خدمت';$out[$name]??=['name'=>$name,'revenue'=>0,'profit'=>0];$revenue=$this->number($s['price']??$s['amount']??0);$out[$name]['revenue']+=$revenue;$out[$name]['profit']+=$revenue-$this->number($s['material_cost']??0);}} return collect($out)->map(fn($r)=>[...$r,'revenue'=>round($r['revenue']),'profit'=>round($r['profit'])])->sortByDesc('revenue')->values(); }
    private function salesTimeline($completed): Collection { $out=[]; foreach($completed as $row){$date=$this->dateOf($row);$out[$date]??=['date'=>$date,'revenue'=>0,'count'=>0];$out[$date]['revenue']+=$this->appointmentValue($row);$out[$date]['count']++;} ksort($out); return collect(array_values($out))->map(fn($row)=>[...$row,'revenue'=>round($row['revenue'])]); }
    private function birthdays(array $filters): Collection { $month=(int)substr($filters['to'],5,2); return Patient::query()->whereNotNull('birth_date')->get()->filter(fn($p)=>preg_match('/(?:-|\/)(\d{1,2})(?:-|\/|$)/',(string)$p->birth_date,$m)&&(int)$m[1]===$month)->map(fn($p)=>['id'=>$p->id,'name'=>trim($p->first_name.' '.$p->last_name),'birth_date'=>$p->birth_date])->values(); }
    private function capacity(float $revenue): array { $limit=max(0,(float)AppSetting::getByKey('report_monthly_capacity',0)); return ['limit'=>round($limit),'revenue'=>round($revenue),'percent'=>$limit?round(min(100,$revenue/$limit*100),1):0]; }
    private function isCompleted($row):bool{return trim((string)$row->done)==='انجام شد';} private function isCancelled($row):bool{$s=(string)$row->status;return str_contains($s,'کنسل')||str_contains($s,'لغو');} private function dateOf($row):string{return ($row->month?:'').'-'.str_pad((string)($row->day_num?:1),2,'0',STR_PAD_LEFT);} private function appointmentValue($row):float{$services=collect($row->services?:[]);$sum=$services->sum(fn($s)=>$this->number($s['price']??$s['amount']??0)*max(1,$this->number($s['quantity']??1)));return $sum?:$this->number($row->amount);} private function number($value):float{return (float)str_replace([',','٬',' '],'',(string)$value);} private function normalizeDate($value):?string{$v=strtr(trim((string)$value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',$v)?$v:null;} private function monthsBetween($from,$to):array{$out=[];[$y,$m]=array_map('intval',explode('-',substr($from,0,7)));$end=substr($to,0,7);while(sprintf('%04d-%02d',$y,$m)<=$end){$out[]=sprintf('%04d-%02d',$y,$m);if(++$m===13){$m=1;$y++;}}return $out;} private function salaryRatio(array $filters):float { $months=$this->monthsBetween($filters['from'],$filters['to']); if(count($months)===1)return max(0,((int)substr($filters['to'],8,2)-(int)substr($filters['from'],8,2)+1)/30); return max(0, count($months)-2 + (31-(int)substr($filters['from'],8,2)+1)/30 + (int)substr($filters['to'],8,2)/30); }
}
