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

class ClinicReportService
{
    public function dashboard(array $filters): array
    {
        $appointments = $this->appointments($filters)->get();
        $completed = $appointments->filter(fn ($row) => $this->isCompleted($row));
        $statuses = $this->statusCounts($appointments);
        $revenue = $completed->sum(fn ($row) => $this->appointmentValue($row));
        $cash = $completed->sum(fn ($row) => max(0, $this->appointmentValue($row) - $this->number($row->debt)));
        $expenses = Expense::query()->whereBetween('occurred_on', [$filters['from'], $filters['to']])->where('type', 'expense')->get();
        $resourceCosts = $this->resourceCosts($filters);
        $expenseTotal = (float) $expenses->sum('amount');
        $forecast = $this->forecast($filters);

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
            'sales_timeline' => $completed->groupBy(fn ($row) => $this->dateOf($row))->map(fn (Collection $rows, $date) => ['date'=>$date,'revenue'=>round($rows->sum(fn($r)=>$this->appointmentValue($r))),'count'=>$rows->count()])->sortKeys()->values(),
            'cancellation' => $statuses,
            'forecast' => $forecast,
            'expenses' => ['total'=>round($expenseTotal),'advertising_total'=>round($expenses->filter(fn($row)=>in_array(mb_strtolower(trim((string)$row->category)), ['advertising','تبلیغات'], true))->sum('amount')),'items'=>$expenses->groupBy('category')->map(fn($rows,$category)=>['category'=>$category,'amount'=>round($rows->sum('amount'))])->values()],
            'resources' => $resourceCosts['resources'],
            'campaigns' => $this->campaigns($appointments, $expenses),
            'channels' => $this->channels($completed, $expenses),
            'loyalty' => $this->loyalty($filters),
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
        return Appointment::query()->where(function(Builder $q) use($filters) {
            [$fm,$fd]=[substr($filters['from'],0,7),(int)substr($filters['from'],8,2)]; [$tm,$td]=[substr($filters['to'],0,7),(int)substr($filters['to'],8,2)];
            $q->where(fn($x)=>$x->where('month','>',$fm)->orWhere(fn($y)=>$y->where('month',$fm)->where('day_num','>=',$fd)))
              ->where(fn($x)=>$x->where('month','<',$tm)->orWhere(fn($y)=>$y->where('month',$tm)->where('day_num','<=',$td)));
        })->when($filters['doctor'],fn($q,$v)=>$q->where('doctor',$v))->when($filters['consultant'],fn($q,$v)=>$q->where('consultant',$v))->when($filters['campaign_id'],fn($q,$v)=>$q->where('campaign_id',$v))->when($filters['status'],fn($q,$v)=>$q->whereIn('status',$v))->when($filters['done'],fn($q,$v)=>$q->whereIn('done',$v));
    }

    private function statusCounts(Collection $rows): array
    {
        $arrived=$rows->filter(fn($r)=>str_contains((string)$r->status,'آمد'))->count();
        $cancelled=$rows->filter(fn($r)=>$this->isCancelled($r))->count();
        $noAnswer=$rows->filter(fn($r)=>str_contains((string)$r->status,'پاسخ نداد'))->count();
        $base=$arrived+$cancelled+$noAnswer;
        return ['arrived'=>$arrived,'cancelled'=>$cancelled,'no_answer'=>$noAnswer,'eligible'=>$base,'rate'=>$base?round((($cancelled+$noAnswer)/$base)*100,1):0];
    }

    private function forecast(array $filters): array
    {
        $month=substr($filters['from'],0,7); [$year,$m]=array_map('intval',explode('-',$month)); $previous=sprintf('%04d-%02d',$m===1?$year-1:$year,$m===1?12:$m-1);
        $prior=Appointment::query()->where('month',$previous)->get(); $rate=$this->statusCounts($prior)['rate']/100;
        $scheduled=$this->appointments($filters)->get()->filter(fn($r)=>str_contains((string)$r->status,'وقت'));
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

    private function campaigns(Collection $appointments, Collection $expenses): Collection { return $expenses->whereNotNull('campaign_id')->groupBy('campaign_id')->map(function($rows,$id)use($appointments){$ap=$appointments->where('campaign_id',(int)$id);$cost=(float)$rows->sum('amount');return ['campaign_id'=>(int)$id,'name'=>$rows->first()->campaign?->name ?? 'کمپین','cost'=>round($cost),'appointments'=>$ap->count(),'cac'=>round($cost/max(1,$ap->count())),'quality'=>$ap->count()?round($ap->filter(fn($r)=>$this->isCompleted($r))->count()/$ap->count()*100,1):0];})->values(); }
    private function channels(Collection $completed, Collection $expenses): Collection { return $completed->groupBy(fn($r)=>$r->source ?: 'بدون کانال')->map(function($rows,$name)use($expenses){$cost=(float)$expenses->where('category','advertising')->sum('amount');return ['name'=>$name,'completed'=>$rows->count(),'revenue'=>round($rows->sum(fn($r)=>$this->appointmentValue($r))),'cost'=>0,'cac'=>0];})->values(); }
    private function loyalty(array $filters): array { $all=Appointment::query()->where('done','انجام شد')->get(); $keys=$all->map(fn($r)=>$r->file_number ?: $r->phone)->filter(); $in=$this->appointments($filters)->get()->filter(fn($r)=>$this->isCompleted($r))->map(fn($r)=>$r->file_number ?: $r->phone)->filter(); $loyal=$in->filter(fn($k)=>$all->filter(fn($r)=>($r->file_number?:$r->phone)===$k)->count()>=2)->unique()->count(); $churn=$keys->unique()->diff($in->unique())->count(); return ['completed_customers'=>$in->unique()->count(),'loyal'=>$loyal,'churned'=>$churn]; }
    private function customers(array $filters): array { $rows=$this->appointments($filters)->get(); return ['new'=>$rows->where('new_customer',true)->unique(fn($r)=>$r->file_number ?: $r->phone)->count(),'old'=>$rows->where('new_customer',false)->unique(fn($r)=>$r->file_number ?: $r->phone)->count()]; }
    private function satisfaction(array $filters): Collection { return SatisfactionAnswer::query()->whereHas('response',fn($q)=>$q->whereBetween('answered_on',[$filters['from'],$filters['to']]))->get()->groupBy('question_key')->map(fn($r)=>['key'=>$r->first()->question_key,'label'=>$r->first()->question_label,'count'=>$r->count(),'percent'=>round($r->avg('score')/5*100,1)])->values(); }
    private function photoQuality(array $filters): Collection { return PhotoQualityReview::query()->whereBetween('reviewed_on',[$filters['from'],$filters['to']])->get()->groupBy('service_tag')->map(fn($r,$tag)=>['tag'=>$tag,'total'=>$r->count(),'qualified'=>$r->where('is_qualified',true)->count(),'percent'=>round($r->where('is_qualified',true)->count()/max(1,$r->count())*100,1)])->values(); }
    private function services(Collection $completed): Collection { return $completed->flatMap(fn($r)=>collect($r->services ?: [])->map(fn($s)=>['name'=>$s['name']??$s['service']??'خدمت','revenue'=>$this->number($s['price']??$s['amount']??0),'profit'=>$this->number($s['price']??$s['amount']??0)-$this->number($s['material_cost']??0)]))->groupBy('name')->map(fn($r,$name)=>['name'=>$name,'revenue'=>round($r->sum('revenue')),'profit'=>round($r->sum('profit'))])->sortByDesc('revenue')->values(); }
    private function birthdays(array $filters): Collection { $month=(int)substr($filters['to'],5,2); return Patient::query()->whereNotNull('birth_date')->get()->filter(fn($p)=>preg_match('/(?:-|\/)(\d{1,2})(?:-|\/|$)/',(string)$p->birth_date,$m)&&(int)$m[1]===$month)->map(fn($p)=>['id'=>$p->id,'name'=>trim($p->first_name.' '.$p->last_name),'birth_date'=>$p->birth_date])->values(); }
    private function capacity(float $revenue): array { $limit=max(0,(float)AppSetting::getByKey('report_monthly_capacity',0)); return ['limit'=>round($limit),'revenue'=>round($revenue),'percent'=>$limit?round(min(100,$revenue/$limit*100),1):0]; }
    private function isCompleted($row):bool{return trim((string)$row->done)==='انجام شد';} private function isCancelled($row):bool{$s=(string)$row->status;return str_contains($s,'کنسل')||str_contains($s,'لغو');} private function dateOf($row):string{return ($row->month?:'').'-'.str_pad((string)($row->day_num?:1),2,'0',STR_PAD_LEFT);} private function appointmentValue($row):float{$services=collect($row->services?:[]);$sum=$services->sum(fn($s)=>$this->number($s['price']??$s['amount']??0)*max(1,$this->number($s['quantity']??1)));return $sum?:$this->number($row->amount);} private function number($value):float{return (float)str_replace([',','٬',' '],'',(string)$value);} private function normalizeDate($value):?string{$v=strtr(trim((string)$value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);return preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',$v)?$v:null;} private function monthsBetween($from,$to):array{$out=[];[$y,$m]=array_map('intval',explode('-',substr($from,0,7)));$end=substr($to,0,7);do{$out[]=sprintf('%04d-%02d',$y,$m);if(++$m===13){$m=1;$y++;}}while(end($out)<=$end);return $out;} private function salaryRatio(array $filters):float { $months=$this->monthsBetween($filters['from'],$filters['to']); if(count($months)===1)return max(0,((int)substr($filters['to'],8,2)-(int)substr($filters['from'],8,2)+1)/30); return max(0, count($months)-2 + (31-(int)substr($filters['from'],8,2)+1)/30 + (int)substr($filters['to'],8,2)/30); }
}
