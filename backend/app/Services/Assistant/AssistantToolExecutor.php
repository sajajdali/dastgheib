<?php

namespace App\Services\Assistant;

use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Models\ServiceFollowup;
use App\Models\Staff;
use App\Models\User;
use App\Reporting\DynamicReports\DynamicReportFilterPipeline;
use App\Services\ClinicReportService;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class AssistantToolExecutor
{
    public function __construct(
        private readonly AssistantToolRegistry $registry,
        private readonly AssistantDateRange $dates,
        private readonly ClinicReportService $reports,
    ) {}

    public function execute(User $user, string $name, array $arguments): array
    {
        if (! $this->registry->allowed($user, $name)) throw new InvalidArgumentException('اجازهٔ دسترسی به این داده را ندارید.');
        return match ($name) {
            'find_doctors' => $this->doctors($arguments),
            'count_doctors' => $this->countDoctors(),
            'count_patients' => $this->countPatients(),
            'count_staff' => $this->countStaff(),
            'count_inventory_items' => $this->countInventoryItems(),
            'count_appointments' => $this->appointments($arguments, false),
            'list_appointments' => $this->appointments($arguments, true),
            'list_service_followups' => $this->followups($arguments),
            'list_followups' => $this->generalFollowups($arguments),
            'search_patients' => $this->patients($arguments),
            'search_people' => $this->people($user, $arguments),
            'preview_doctor_patients' => $this->doctorPatients($arguments),
            'find_patient_photos' => $this->patientPhotos($arguments),
            'open_patient_result' => $this->openPatientResult($user, $arguments),
            'query_clinic_records' => app(AssistantDataCatalog::class)->query($user, $arguments),
            'inspect_clinic_record' => app(AssistantDataCatalog::class)->inspect($user, (string) ($arguments['dataset'] ?? ''), (int) ($arguments['id'] ?? 0)),
            'get_financial_metric' => $this->financial($arguments),
            'get_doctor_revenue' => $this->doctorRevenue($arguments),
            'open_clinic_page' => $this->page($user, $arguments),
            default => throw new InvalidArgumentException('ابزار نامعتبر است.'),
        };
    }

    private function doctors(array $arguments): array
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        $query = Doctor::query()->when($name !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($name, '%_\\').'%'));
        $count = (clone $query)->count();
        $rows = $query->orderBy('name')->limit(50)->pluck('name')->all();
        return $this->result(['count' => $count, 'doctors' => $rows, 'truncated' => $count > 50],
            ['type' => 'list', 'title' => 'پزشکان · '.$count.' نفر', 'items' => $rows]);
    }

    private function countDoctors(): array
    {
        $count = Doctor::query()->count();
        return $this->result(['count'=>$count,'entity'=>'doctors','scope'=>'all_registered_doctors'],
            ['type'=>'metric','title'=>'تعداد پزشکان','value'=>$count,'unit'=>'نفر','subtitle'=>'پزشکان ثبت‌شدهٔ کلینیک']);
    }

    private function countPatients(): array
    {
        $count = Patient::query()->count();
        return $this->result(['count'=>$count,'entity'=>'patients','scope'=>'active_patient_files'],
            ['type'=>'metric','title'=>'تعداد پرونده‌های فعال','value'=>$count,'unit'=>'پرونده','subtitle'=>'پرونده‌های فعال کلینیک']);
    }

    private function countStaff(): array
    {
        $count = Staff::query()->count();
        return $this->result(['count'=>$count,'entity'=>'staff','scope'=>'all_registered_staff'],
            ['type'=>'metric','title'=>'تعداد پرسنل','value'=>$count,'unit'=>'نفر','subtitle'=>'پرسنل ثبت‌شدهٔ کلینیک']);
    }

    private function countInventoryItems(): array
    {
        $count = Inventory::query()->where('active', true)->count();
        return $this->result(['count'=>$count,'entity'=>'inventory_items','scope'=>'active_catalog_items_including_services_and_products'],
            ['type'=>'metric','title'=>'اقلام فعال انبار','value'=>$count,'unit'=>'مورد','subtitle'=>'خدمات و کالاهای فعال']);
    }

    private function appointments(array $arguments, bool $list): array
    {
        $range = $this->dates->resolve($arguments);
        $doctorInput = trim((string) ($arguments['doctor'] ?? ''));
        $doctor = null;
        if ($doctorInput !== '') {
            $matches = Doctor::query()->where('name', 'like', '%'.addcslashes($doctorInput, '%_\\').'%')->limit(3)->pluck('name');
            if ($matches->count() > 1) return $this->result(['error' => 'نام پزشک مبهم است.', 'matches' => $matches->all()]);
            $doctor = $matches->first() ?: $doctorInput;
        }
        $status = (string) ($arguments['status'] ?? 'active');
        $query = Appointment::query()->where(fn (Builder $q) => $q->whereNull('file_number')->orWhere('file_number', 'not like', 'LOADTEST-%'))
            ->where(fn (Builder $q) => $this->meaningfulAppointment($q))
            ->where(function (Builder $q) use ($range) {
                $fm = substr($range['from'], 0, 7); $fd = (int) substr($range['from'], 8, 2);
                $tm = substr($range['to'], 0, 7); $td = (int) substr($range['to'], 8, 2);
                $q->where(fn (Builder $x) => $x->where('month', '>', $fm)->orWhere(fn (Builder $y) => $y->where('month', $fm)->where('day_num', '>=', $fd)))
                    ->where(fn (Builder $x) => $x->where('month', '<', $tm)->orWhere(fn (Builder $y) => $y->where('month', $tm)->where('day_num', '<=', $td)));
            });
        if ($doctor) $query->where('doctor', 'like', '%'.addcslashes($doctor, '%_\\').'%');
        if ($status === 'active') $query->whereNotIn('status', ['کنسل شد', 'پاسخ نداد', 'انتقال یافت']);
        elseif ($status === 'cancelled') $query->where('status', 'کنسل شد');
        elseif ($status === 'no_answer') $query->where('status', 'پاسخ نداد');
        elseif ($status === 'arrived') $query->where('status', 'آمد');
        elseif ($status === 'scheduled') $query->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', '')->orWhere('status', 'رزرو شد'));
        elseif ($status === 'followup') $query->where('done', 'پیگیری');
        elseif ($status !== 'all') throw new InvalidArgumentException('وضعیت نوبت نامعتبر است.');
        $count = (clone $query)->count();
        $label = $range['from'] === $range['to'] ? $range['from'] : $range['from'].' تا '.$range['to'];
        if (! $list) return $this->result(['count' => $count, 'period' => $label, 'doctor' => $doctor, 'status' => $status],
            ['type' => 'metric', 'title' => 'تعداد نوبت‌ها', 'value' => $count, 'subtitle' => $label.($doctor ? ' · '.$doctor : '')]);

        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));
        $rows = (clone $query)->orderBy('month')->orderBy('day_num')->orderBy('sort_order')->limit($limit)
            ->get(['id', 'month', 'day_num', 'lastname', 'doctor', 'status', 'time', 'file_number'])
            ->map(fn (Appointment $a) => [
                'date' => $a->month.'-'.str_pad((string) $a->day_num, 2, '0', STR_PAD_LEFT),
                'time' => (string) ($a->time ?? ''), 'patient' => (string) ($a->lastname ?? ''),
                'doctor' => (string) ($a->doctor ?? ''), 'status' => (string) ($a->status ?? ''),
                'file_number' => (string) ($a->file_number ?? ''),
            ])->all();
        return $this->result(['count' => $count, 'period' => $label, 'doctor' => $doctor, 'rows' => $rows, 'truncated' => $count > $limit],
            ['type' => 'table', 'title' => 'نوبت‌ها · '.$label, 'count' => $count, 'columns' => ['date'=>'تاریخ','time'=>'ساعت','patient'=>'مراجع','doctor'=>'پزشک','status'=>'وضعیت'], 'rows' => $rows, 'page' => 'appointments', 'date' => $range['from'] === $range['to'] ? $range['from'] : null]);
    }

    private function followups(array $arguments): array
    {
        $scope = (string) ($arguments['scope'] ?? 'today');
        if (! in_array($scope, ['today', 'overdue', 'pending', 'all'], true)) throw new InvalidArgumentException('محدودهٔ پیگیری نامعتبر است.');
        $today = now('Asia/Tehran')->toDateString();
        $query = ServiceFollowup::query();
        $term = trim((string) ($arguments['query'] ?? ''));
        if ($term !== '') {
            $escaped = addcslashes($term, '%_\\');
            $query->where(fn (Builder $q) => $q->where('patient_name', 'like', '%'.$escaped.'%')
                ->orWhere('service_name', 'like', '%'.$escaped.'%'));
        }
        if ($scope === 'today') $query->whereDate('due_date', $today);
        if ($scope === 'overdue') $query->whereDate('due_date', '<', $today)->where('status', 'pending');
        if ($scope === 'pending') $query->where('status', 'pending');
        $count = (clone $query)->count();
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));
        $rows = $query->orderBy('due_date')->limit($limit)->get(['patient_name','service_name','due_date','status'])
            ->map(fn (ServiceFollowup $f) => ['patient' => (string) $f->patient_name, 'service' => (string) $f->service_name,
                'due' => (string) $f->due_date?->format('Y-m-d'), 'status' => (string) $f->status])->all();
        return $this->result(['count'=>$count,'scope'=>$scope,'rows'=>$rows,'truncated'=>$count>$limit],
            ['type'=>'table','title'=>'پیگیری‌های خدمت','count'=>$count,'columns'=>['patient'=>'مراجع','service'=>'خدمت','due'=>'سررسید میلادی','status'=>'وضعیت'],'rows'=>$rows,'page'=>'service_followups']);
    }

    private function meaningfulAppointment(Builder $query): void
    {
        foreach ([
            'lastname', 'gender', 'phone', 'file_number', 'status', 'doctor', 'consultant',
            'source', 'description', 'doctor_note', 'done', 'payment_method',
            'payment_account', 'payment_link', 'referrer_phone', 'appointment_sms', 'info_sms',
        ] as $column) {
            $query->orWhere(fn (Builder $q) => $q->whereNotNull($column)->where($column, '<>', ''));
        }
        $query->orWhere('new_customer', true)
            ->orWhere('payment_link_sent_count', '>', 0)
            ->orWhere('amount', '>', 0)->orWhere('debt', '>', 0)->orWhere('discount', '>', 0)
            ->orWhere(function (Builder $q) {
                $q->whereNotNull('services');
                if ($q->getConnection()->getDriverName() === 'mysql') {
                    $q->whereRaw('services REGEXP ?', ['"name"[[:space:]]*:[[:space:]]*"[^"]+"']);
                } else {
                    $q->where('services', 'like', '%"name":"_%');
                }
            })
            ->orWhere(fn (Builder $q) => $q->whereNotNull('service_types')->whereNotIn('service_types', ['', '[]', 'null']));
    }

    private function generalFollowups(array $arguments): array
    {
        $scope = (string) ($arguments['scope'] ?? 'today');
        if (! in_array($scope, ['today','overdue','pending','all'], true)) throw new InvalidArgumentException('محدودهٔ پیگیری نامعتبر است.');
        $today = now('Asia/Tehran')->toDateString();
        $term = trim((string) ($arguments['query'] ?? ''));
        $matches = [];
        foreach (Campaign::query()->get(['name','active','ui_payload']) as $campaign) {
            $payload = is_array($campaign->ui_payload) ? $campaign->ui_payload : [];
            if ($scope !== 'all' && (! $campaign->active || in_array(strtolower((string) ($payload['campaignStatus'] ?? 'active')), ['archived','archive'], true))) continue;
            foreach ((array) ($payload['rows'] ?? []) as $row) {
                if (! is_array($row)) continue;
                $name = trim((string) ($row['fullName'] ?? ''));
                if ($term !== '' && mb_stripos($name, $term) === false) continue;
                $due = strtr(trim((string) ($row['followUpDate'] ?? '')), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);
                $pending = empty($row['appointmentRegistered']) && ! in_array(trim((string) ($row['status'] ?? '')), ['پاسخ داد','اشتباه'], true);
                if ($scope === 'today' && ! ($pending && $due === $today)) continue;
                if ($scope === 'overdue' && ! ($pending && $due !== '' && $due < $today)) continue;
                if ($scope === 'pending' && ! $pending) continue;
                $matches[] = ['name'=>$name,'due'=>$due,'status'=>(string) ($row['status'] ?? ''),
                    'consultant'=>(string) ($row['consultant'] ?? ''),'campaign'=>(string) ($payload['title'] ?? $campaign->name)];
            }
        }
        usort($matches, fn (array $a, array $b) => strcmp($a['due'], $b['due']));
        $count = count($matches);
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));
        $rows = array_slice($matches, 0, $limit);
        return $this->result(['count'=>$count,'scope'=>$scope,'query'=>$term,'rows'=>$rows,'truncated'=>$count>$limit],
            ['type'=>'table','title'=>'پیگیری‌ها','count'=>$count,'columns'=>['name'=>'نام','due'=>'تاریخ میلادی','status'=>'وضعیت','consultant'=>'مشاور','campaign'=>'کمپین'],'rows'=>$rows,'page'=>'followups']);
    }

    private function patients(array $arguments): array
    {
        $term = trim((string) ($arguments['query'] ?? ''));
        if (mb_strlen($term) < 2) throw new InvalidArgumentException('برای جست‌وجوی پرونده حداقل دو حرف لازم است.');
        $escaped = addcslashes($term, '%_\\');
        $query = Patient::query()->where(fn (Builder $q) => $q->where('first_name','like','%'.$escaped.'%')
            ->orWhere('last_name','like','%'.$escaped.'%')->orWhere('file_number','like','%'.$escaped.'%'));
        $count = (clone $query)->count();
        $rows = $query->limit(20)->get(['id','first_name','last_name','file_number'])->map(fn (Patient $p) =>
            ['name'=>trim($p->first_name.' '.$p->last_name),'file_number'=>(string) $p->file_number])->all();
        return $this->result(['count'=>$count,'rows'=>$rows,'truncated'=>$count>20],
            ['type'=>'table','title'=>'پرونده‌ها','count'=>$count,'columns'=>['name'=>'نام','file_number'=>'شماره پرونده'],'rows'=>$rows,
                'row_actions'=>array_map(fn (array $row) => $row['file_number'] !== ''
                    ? ['page'=>'patient_profile','file_number'=>$row['file_number'],'label'=>'مشاهده پرونده'] : null, $rows),
                'page'=>'patients']);
    }

    private function people(User $user, array $arguments): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', (string) ($arguments['query'] ?? '')));
        if (mb_strlen($term) < 2) throw new InvalidArgumentException('برای جست‌وجوی شخص حداقل دو حرف لازم است.');
        $tokens = array_values(array_filter(explode(' ', $term)));
        $rows = [];
        $count = 0;
        if ($user->can('patients.view')) {
            $patients = Patient::query();
            foreach ($tokens as $token) {
                $pattern = '%'.addcslashes($token, '%_\\').'%';
                $patients->where(fn (Builder $q) => $q->where('first_name', 'like', $pattern)
                    ->orWhere('last_name', 'like', $pattern)->orWhere('file_number', 'like', $pattern));
            }
            $count += (clone $patients)->count();
            foreach ($patients->orderBy('last_name')->orderBy('first_name')->limit(20)->get(['id','first_name','last_name','file_number']) as $patient) {
                $rows[] = ['type'=>'پرونده بیمار', 'name'=>trim($patient->first_name.' '.$patient->last_name),
                    'identifier'=>(string) $patient->file_number, 'action'=>$patient->file_number
                        ? ['page'=>'patient_profile','file_number'=>(string) $patient->file_number,'label'=>'باز کردن پرونده'] : null];
            }
        }
        if ($user->can('settings.users')) {
            $users = User::query();
            foreach ($tokens as $token) $users->where('name', 'like', '%'.addcslashes($token, '%_\\').'%');
            $count += (clone $users)->count();
            foreach ($users->orderBy('name')->limit(20)->get(['id','name']) as $person) {
                $rows[] = ['type'=>'کاربر نرم‌افزار', 'name'=>(string) $person->name, 'identifier'=>'#'.$person->id, 'action'=>null];
            }
        }
        return $this->result(['count'=>$count,'query'=>$term,'rows'=>array_map(fn ($row) => array_diff_key($row, ['action'=>true]), $rows),
            'truncated'=>$count>count($rows)],
            ['type'=>'table','title'=>'نتیجهٔ جست‌وجوی «'.$term.'»','count'=>$count,
                'columns'=>['type'=>'نوع','name'=>'نام','identifier'=>'شماره پرونده / شناسه'],
                'rows'=>$rows,'row_actions'=>array_column($rows, 'action')]);
    }

    private function doctorPatients(array $arguments): array
    {
        $needle = trim((string) ($arguments['doctor'] ?? ''));
        if (mb_strlen($needle) < 2) throw new InvalidArgumentException('نام پزشک را مشخص کنید.');
        $matches = Doctor::query()->where('name', 'like', '%'.addcslashes($needle, '%_\\').'%')
            ->orderBy('name')->limit(10)->pluck('name');
        if ($matches->isEmpty()) return $this->result(['count'=>0,'doctor'=>$needle,'error'=>'پزشکی با این نام پیدا نشد.']);
        $exact = $matches->first(fn ($name) => mb_strtolower(trim($name)) === mb_strtolower($needle));
        if ($matches->count() > 1 && ! $exact) return $this->result(['error'=>'نام پزشک مبهم است.','matches'=>$matches->all()]);
        $doctor = $exact ?: $matches->first();
        $period = (string) ($arguments['period'] ?? 'all');
        $range = $period === 'all'
            ? ['from'=>'1300-01-01','to'=>$this->dates->resolve(['period'=>'today'])['to']]
            : $this->dates->resolve($arguments);
        $filters = ['scope'=>'patients', 'reportDate'=>$range, 'multi'=>['doctor'=>[$doctor]]];
        $query = app(DynamicReportFilterPipeline::class)->query($filters);
        $count = (clone $query)->count();
        $patients = $query->limit(20)->get(['id','first_name','last_name','file_number']);
        $rows = $patients->map(fn (Patient $patient) => [
            'name'=>trim($patient->first_name.' '.$patient->last_name),
            'file_number'=>(string) $patient->file_number,
        ])->all();
        $actions = $patients->map(fn (Patient $patient) => $patient->file_number
            ? ['page'=>'patient_profile','file_number'=>(string) $patient->file_number,'label'=>'باز کردن پرونده'] : null)->all();
        $report = ['doctor'=>$doctor,'from'=>$range['from'],'to'=>$range['to']];
        return $this->result(['doctor'=>$doctor,'count'=>$count,'period'=>$range,'rows'=>$rows,
            'truncated'=>$count>20,'report_available'=>true],
            ['type'=>'table','title'=>'بیماران '.$doctor.' · '.$range['from'].' تا '.$range['to'],
                'count'=>$count,'columns'=>['name'=>'نام بیمار','file_number'=>'شماره پرونده'],
                'rows'=>$rows,'row_actions'=>$actions,'page'=>'report_builder','report'=>$report,
                'label'=>'نمایش گزارش کامل در گزارش‌ساز']);
    }

    private function patientPhotos(array $arguments): array
    {
        $fileNumber = trim((string) ($arguments['file_number'] ?? ''));
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($arguments['patient'] ?? '')));
        $stage = (string) ($arguments['stage'] ?? 'all');
        if (! in_array($stage, ['before','after','all'], true)) throw new InvalidArgumentException('نوع عکس نامعتبر است.');
        if ($fileNumber === '' && mb_strlen($name) < 2) throw new InvalidArgumentException('نام بیمار یا شماره پرونده را مشخص کنید.');
        $people = Patient::query();
        if ($fileNumber !== '') $people->where('file_number', $fileNumber);
        else foreach (array_filter(explode(' ', $name)) as $token) {
            $pattern = '%'.addcslashes($token, '%_\\').'%';
            $people->where(fn (Builder $q) => $q->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern));
        }
        $count = (clone $people)->count();
        if ($count === 0) return $this->result(['count'=>0,'error'=>'پرونده‌ای با این مشخصات پیدا نشد.']);
        if ($count > 1) {
            $choices = $people->orderBy('last_name')->limit(20)->get(['id','first_name','last_name','file_number'])
                ->map(fn (Patient $person) => ['name'=>trim($person->first_name.' '.$person->last_name),
                    'file_number'=>(string) $person->file_number])->all();
            $actions = array_map(fn (array $person) => $person['file_number'] !== ''
                ? ['prompt'=>'عکس‌های '.(['before'=>'قبل','after'=>'بعد','all'=>'همه'][$stage]).' پروندهٔ شماره '.$person['file_number'].' را نشان بده',
                    'label'=>'انتخاب این پرونده'] : null, $choices);
            return $this->result(['ambiguous'=>true,'count'=>$count,'patients'=>$choices,'truncated'=>$count>20],
                ['type'=>'table','title'=>'کدام پرونده را می‌خواهید؟','count'=>$count,
                    'columns'=>['name'=>'نام','file_number'=>'شماره پرونده'],'rows'=>$choices,'row_actions'=>$actions]);
        }
        $person = $people->firstOrFail();
        $query = PatientMedia::query()->with('folder.parent.parent')->where('patient_id', $person->id)->where('media_type', 'image');
        if ($stage !== 'all') $query->where('comparison_stage', $stage);
        $photoCount = (clone $query)->count();
        $items = $query->orderByDesc('created_at')->limit(30)->get(['id','patient_id','folder_id','original_name','media_type','comparison_stage','photo_angle_label','created_at']);
        $rows = $items->map(function (PatientMedia $photo): array {
            $folder = $photo->folder;
            $date = null;
            for ($depth = 0; $folder && $depth < 5; $depth++, $folder = $folder->parent) {
                if ($folder->folder_date) { $date = (string) $folder->folder_date; break; }
            }
            return ['date'=>$date ?: $photo->created_at?->format('Y-m-d'),
                'date_type'=>$date ? 'شمسی' : 'میلادی',
                'stage'=>['before'=>'قبل','after'=>'بعد'][$photo->comparison_stage] ?? 'بدون برچسب',
                'angle'=>(string) ($photo->photo_angle_label ?: $photo->original_name ?: 'عکس')];
        })->all();
        $actions = $items->map(fn (PatientMedia $photo) => $person->file_number
            ? ['page'=>'patient_photo','file_number'=>(string) $person->file_number,'media_id'=>(int) $photo->id,'label'=>'باز کردن عکس'] : null)->all();
        return $this->result(['patient'=>trim($person->first_name.' '.$person->last_name),
            'file_number'=>(string) $person->file_number,'count'=>$photoCount,'stage'=>$stage,
            'rows'=>$rows,'truncated'=>$photoCount>30],
            ['type'=>'table','title'=>'عکس‌های '.(['before'=>'قبل','after'=>'بعد','all'=>'پرونده'][$stage]).' '.trim($person->first_name.' '.$person->last_name),
                'count'=>$photoCount,'columns'=>['date'=>'تاریخ','date_type'=>'تقویم','stage'=>'مرحله','angle'=>'زاویه / عنوان'],
                'rows'=>$rows,'row_actions'=>$actions]);
    }

    private function openPatientResult(User $user, array $arguments): array
    {
        $fileNumber = trim((string) ($arguments['file_number'] ?? ''));
        if ($fileNumber === '') throw new InvalidArgumentException('شماره پرونده مشخص نیست.');
        $person = Patient::query()->where('file_number', $fileNumber)->first();
        if (! $person) throw new InvalidArgumentException('پرونده پیدا نشد.');
        $mediaId = $arguments['media_id'] ?? null;
        $page = 'patient_profile';
        if ($mediaId !== null) {
            if (! $user->can('photos.view')) throw new InvalidArgumentException('اجازهٔ مشاهدهٔ عکس‌ها را ندارید.');
            $validPhoto = PatientMedia::query()->where('patient_id', $person->id)->where('media_type', 'image')->whereKey((int) $mediaId)->exists();
            if (! $validPhoto) throw new InvalidArgumentException('عکس در این پرونده پیدا نشد.');
            $page = 'patient_photo';
        }
        $action = ['type'=>'navigation','title'=>$page === 'patient_photo' ? 'عکس انتخاب‌شده' : 'پروندهٔ '.trim($person->first_name.' '.$person->last_name),
            'label'=>$page === 'patient_photo' ? 'باز کردن عکس' : 'باز کردن پرونده',
            'page'=>$page,'file_number'=>$fileNumber,'media_id'=>$mediaId];

        return $this->result(['found'=>true,'patient'=>trim($person->first_name.' '.$person->last_name),
            'file_number'=>$fileNumber,'media_id'=>$mediaId], $action);
    }

    private function financial(array $arguments): array
    {
        $range = $this->dates->resolve($arguments);
        $metric = (string) ($arguments['metric'] ?? 'recognized_revenue');
        $labels = ['recognized_revenue'=>'درآمد شناسایی‌شده','expenses'=>'هزینه‌ها','net_profit'=>'سود خالص'];
        $definitions = [
            'recognized_revenue'=>'جمع خدمات انجام‌شده و تسویه‌شده طبق گزارش مالی برنامه',
            'expenses'=>'جمع هزینه‌های ثبت‌شده در بازه',
            'net_profit'=>'درآمد شناسایی‌شده منهای هزینه‌ها، دستمزد پزشک و پرسنل و مواد مصرفی',
        ];
        if (! isset($labels[$metric])) throw new InvalidArgumentException('شاخص مالی نامعتبر است.');
        $filters = $this->reports->normalizeFilters($range);
        $kpis = $this->reports->dashboard($filters)['kpis'];
        $value = (int) ($kpis[$metric] ?? 0);
        return $this->result(['metric'=>$metric,'value'=>$value,'unit'=>'تومان','from'=>$range['from'],'to'=>$range['to'],'definition'=>$definitions[$metric]],
            ['type'=>'metric','title'=>$labels[$metric],'value'=>$value,'unit'=>'تومان','subtitle'=>$range['from'].' تا '.$range['to'].' · '.$definitions[$metric],'page'=>'reports']);
    }

    private function doctorRevenue(array $arguments): array
    {
        $range = $this->dates->resolve(['period'=>'this_month', ...$arguments]);
        $report = $this->reports->doctorPerformanceReport($this->reports->normalizeFilters($range));
        $rows = collect($report['doctors'] ?? [])->map(fn (array $row) => [
            'doctor'=>(string) $row['name'],
            'revenue'=>(int) $row['revenue'],
        ]);
        $needle = trim((string) ($arguments['doctor'] ?? ''));
        if ($needle !== '') {
            $exact = $rows->filter(fn (array $row) => mb_strtolower($row['doctor']) === mb_strtolower($needle));
            $rows = $exact->isNotEmpty() ? $exact : $rows->filter(fn (array $row) => mb_stripos($row['doctor'], $needle) !== false);
            if ($rows->count() > 1) return $this->result(['error'=>'نام پزشک مبهم است.','matches'=>$rows->pluck('doctor')->all()]);
        }
        $rows = $rows->sortByDesc('revenue')->values()->all();
        $label = $range['from'].' تا '.$range['to'];
        $definition = 'آوردهٔ خدمات انجام‌شده و تسویه‌شدهٔ منتسب به پزشک؛ حقوق و پورسانت نیست';
        return $this->result([
            'period'=>$label,'unit'=>'تومان','definition'=>$definition,
            'count'=>count($rows),'doctors'=>$rows,
        ], [
            'type'=>'table','title'=>'آوردهٔ هر پزشک · '.$label,'count'=>count($rows),
            'columns'=>['doctor'=>'پزشک','revenue'=>'آورده (تومان)'],'rows'=>$rows,
            'page'=>'reports','definition'=>$definition,
        ]);
    }

    private function page(User $user, array $arguments): array
    {
        $page = (string) ($arguments['page'] ?? '');
        $destination = (new AssistantNavigationCatalog)->destination($user, $page);
        if ($destination === null) throw new InvalidArgumentException('اجازهٔ باز کردن این بخش را ندارید.');
        $date = (string) ($arguments['date'] ?? '');
        if ($date !== '' && ! preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) $date = '';
        return $this->result(['page'=>$page,'label'=>$destination[0]], ['type'=>'navigation','title'=>'نمایش در برنامه','label'=>'رفتن به '.$destination[0],'page'=>$page,'date'=>$date ?: null]);
    }

    private function result(array $model, ?array $presentation = null): array
    {
        return ['model'=>$model,'presentation'=>$presentation];
    }
}
