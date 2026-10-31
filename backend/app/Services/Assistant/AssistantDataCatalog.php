<?php

namespace App\Services\Assistant;

use App\Models\Appointment;
use App\Models\AppointmentFinancialTransaction;
use App\Models\AttendanceMonth;
use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use App\Models\BeautyAnnotation;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\InventoryMovement;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Models\ResourceEarningLine;
use App\Models\Service;
use App\Models\ServiceFollowup;
use App\Models\SatisfactionResponse;
use App\Models\SatisfactionAnswer;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class AssistantDataCatalog
{
    private const DATASETS = [
        'patients' => ['پرونده‌ها', Patient::class, 'patients.view', 'patients', ['first_name'=>'نام','last_name'=>'نام خانوادگی','file_number'=>'شماره پرونده','gender'=>'جنسیت','city'=>'شهر','customer_level'=>'سطح مشتری','financial_status'=>'وضعیت مالی']],
        'appointments' => ['نوبت‌ها', Appointment::class, 'appointments.view', 'appointments', ['month'=>'ماه شمسی','day_num'=>'روز','lastname'=>'نام مراجع','doctor'=>'پزشک','status'=>'وضعیت','time'=>'ساعت','file_number'=>'شماره پرونده','done'=>'انجام کار','consultant'=>'مشاور','source'=>'منبع']],
        'doctors' => ['پزشکان', Doctor::class, 'resources.view|appointments.view', 'settings_doctors', ['name'=>'نام']],
        'staff' => ['پرسنل', Staff::class, 'resources.view', 'settings_staff', ['name'=>'نام']],
        'inventory' => ['انبار', Inventory::class, 'inventory.view', 'inventory', ['name'=>'نام','stock'=>'موجودی','min_stock'=>'حداقل موجودی','price'=>'قیمت','active'=>'فعال']],
        'inventory_sections' => ['بخش‌های خدمات', InventorySection::class, 'inventory.view', 'inventory', ['parent_id'=>'شناسه والد','level'=>'سطح','name'=>'نام']],
        'inventory_movements' => ['گردش انبار', InventoryMovement::class, 'inventory.view', 'inventory', ['inventory_id'=>'شناسه کالا','inventory_name'=>'کالا','quantity'=>'مقدار','type'=>'نوع','occurred_at'=>'تاریخ']],
        'patient_media' => ['رسانه‌های پرونده', PatientMedia::class, 'patients.view&photos.view', 'photos', ['patient_id'=>'شناسه بیمار','media_type'=>'نوع رسانه','comparison_stage'=>'مرحله قبل/بعد','photo_angle_label'=>'زاویه','original_name'=>'نام فایل','created_at'=>'تاریخ ثبت']],
        'service_followups' => ['پیگیری خدمت', ServiceFollowup::class, 'followups.view', 'service_followups', ['appointment_id'=>'شناسه نوبت','inventory_id'=>'شناسه خدمت','patient_name'=>'مراجع','service_name'=>'خدمت','due_date'=>'سررسید میلادی','status'=>'وضعیت']],
        'campaigns' => ['کمپین‌ها', Campaign::class, 'followups.view', 'followups', ['name'=>'نام','starts_on'=>'شروع','ends_on'=>'پایان','active'=>'فعال']],
        'channels' => ['کانال‌ها', Channel::class, 'resources.view', 'settings_channels', ['name'=>'نام']],
        'expenses' => ['هزینه‌ها', Expense::class, 'bills.view', 'expenses', ['campaign_id'=>'شناسه کمپین','title'=>'عنوان','category'=>'دسته','amount'=>'مبلغ تومان','occurred_on'=>'تاریخ','payment_status'=>'وضعیت پرداخت','party'=>'طرف حساب']],
        'tickets' => ['تیکت‌ها', Ticket::class, 'tickets.view', 'tickets', ['subject'=>'عنوان','date'=>'تاریخ','owner'=>'مسئول','priority'=>'اولویت','status'=>'وضعیت']],
        'services' => ['خدمات ثبت‌شده', Service::class, 'services.view', 'services', ['service'=>'خدمت','doctor'=>'پزشک','date'=>'تاریخ','status'=>'وضعیت','file_number'=>'شماره پرونده']],
        'attendance' => ['حضور و غیاب ماهانه', AttendanceMonth::class, 'attendance.view', 'attendance', ['name'=>'نام','resource_type'=>'نوع نیرو','resource_id'=>'شناسه نیرو','year'=>'سال','month'=>'ماه','daily_hours'=>'ساعات روزانه']],
        'earnings' => ['ریز کارکرد و پورسانت', ResourceEarningLine::class, 'payroll.view', 'payroll', ['resource_type'=>'نوع نیرو','resource_id'=>'شناسه نیرو','resource_name'=>'نام نیرو','month'=>'ماه شمسی','service_name'=>'خدمت','amount'=>'مبلغ تومان','status'=>'وضعیت']],
        'financial_transactions' => ['تراکنش‌های مالی نوبت', AppointmentFinancialTransaction::class, 'reports.financial', 'reports', ['appointment_id'=>'شناسه نوبت','patient_id'=>'شناسه بیمار','amount'=>'مبلغ تومان','due_date'=>'سررسید','occurred_at'=>'تاریخ وقوع']],
        'wallet_transactions' => ['تراکنش‌های کیف پول', WalletTransaction::class, 'patients.view&patients.wallet', 'patients', ['patient_id'=>'شناسه بیمار','appointment_id'=>'شناسه نوبت','type'=>'نوع','amount'=>'مبلغ تومان','source_type'=>'منبع','created_at'=>'تاریخ']],
        'beauty_annotations' => ['یادداشت‌های زیبایار', BeautyAnnotation::class, 'beauty.view', 'beauty', ['patient_id'=>'شناسه بیمار','patient_media_id'=>'شناسه رسانه','area'=>'ناحیه','problem'=>'مسئله','status'=>'وضعیت','annotation_date'=>'تاریخ']],
        'automatic_sms_projects' => ['پروژه‌های پیامک خودکار', AutomaticSmsProject::class, 'settings.manage', 'automation', ['name'=>'نام','is_active'=>'فعال']],
        'automatic_sms_scenarios' => ['سناریوهای پیامک خودکار', AutomaticSmsScenario::class, 'settings.manage', 'automation', ['project_id'=>'شناسه پروژه','name'=>'نام','inventory_tag'=>'برچسب خدمت','is_active'=>'فعال']],
        'satisfaction_responses' => ['پاسخ‌های رضایت‌سنجی', SatisfactionResponse::class, 'settings.view', 'satisfaction', ['patient_id'=>'شناسه بیمار','appointment_id'=>'شناسه نوبت','answered_on'=>'تاریخ پاسخ']],
        'satisfaction_answers' => ['جزئیات رضایت‌سنجی', SatisfactionAnswer::class, 'settings.view', 'satisfaction', ['satisfaction_response_id'=>'شناسه پاسخ','question_label'=>'سؤال','answer_value'=>'پاسخ','score'=>'امتیاز']],
        'users' => ['کاربران نرم‌افزار', User::class, 'settings.users', 'settings', ['name'=>'نام','nickname'=>'نام نمایشی','access_blocked'=>'دسترسی مسدود']],
    ];

    private const RELATIONS = [
        'patients' => [
            ['appointments', 'file_number', 'file_number', 'نوبت‌ها'],
            ['patient_media', 'patient_id', 'id', 'عکس‌ها و رسانه‌ها'],
            ['wallet_transactions', 'patient_id', 'id', 'تراکنش‌های کیف پول'],
            ['beauty_annotations', 'patient_id', 'id', 'یادداشت‌های زیبایار'],
            ['satisfaction_responses', 'patient_id', 'id', 'رضایت‌سنجی'],
        ],
        'appointments' => [
            ['patients', 'file_number', 'file_number', 'پروندهٔ مراجع'],
            ['service_followups', 'appointment_id', 'id', 'پیگیری‌های خدمت'],
            ['financial_transactions', 'appointment_id', 'id', 'تراکنش‌های مالی'],
            ['wallet_transactions', 'appointment_id', 'id', 'کیف پول مرتبط'],
        ],
        'doctors' => [['appointments', 'doctor', 'name', 'نوبت‌های پزشک']],
        'inventory' => [
            ['inventory_movements', 'inventory_id', 'id', 'گردش کالا'],
            ['service_followups', 'inventory_id', 'id', 'پیگیری‌های این خدمت'],
        ],
        'campaigns' => [['expenses', 'campaign_id', 'id', 'هزینه‌های کمپین']],
        'automatic_sms_projects' => [['automatic_sms_scenarios', 'project_id', 'id', 'سناریوها']],
        'satisfaction_responses' => [['satisfaction_answers', 'satisfaction_response_id', 'id', 'پاسخ‌های سؤال‌ها']],
    ];

    public function schemaDescription(User $user): string
    {
        return collect(self::DATASETS)->filter(fn (array $set, string $key) => $this->allowed($user, $key))
            ->map(fn (array $set, string $key) => $key.' (id, '.implode(', ', array_keys($set[4])).')')->implode('; ');
    }

    public function names(User $user): array
    {
        return array_values(array_filter(array_keys(self::DATASETS), fn ($name) => $this->allowed($user, $name)));
    }

    public function fields(User $user): array
    {
        return collect(self::DATASETS)->filter(fn (array $set, string $key) => $this->allowed($user, $key))
            ->flatMap(fn (array $set) => ['id', ...array_keys($set[4])])->unique()->values()->all();
    }

    public function allowed(User $user, string $dataset): bool
    {
        $permission = self::DATASETS[$dataset][2] ?? null;
        if ($permission === null) return false;
        foreach (explode('&', $permission) as $group) {
            if (! collect(explode('|', $group))->contains(fn ($name) => $user->can($name))) return false;
        }
        return true;
    }

    public function query(User $user, array $arguments): array
    {
        $dataset = (string) ($arguments['dataset'] ?? '');
        if (! isset(self::DATASETS[$dataset])) throw new InvalidArgumentException('مجموعهٔ داده نامعتبر است.');
        if (! $this->allowed($user, $dataset)) throw new InvalidArgumentException('اجازهٔ دسترسی به این داده را ندارید.');
        [$label, $model, , $page, $columns] = self::DATASETS[$dataset];
        if ($page !== null && ! (new AssistantNavigationCatalog)->destination($user, $page)) $page = null;
        $columns = ['id'=>'شناسه', ...$columns];
        $filters = $arguments['filters'] ?? [];
        if (! is_array($filters) || count($filters) > 4) throw new InvalidArgumentException('حداکثر چهار فیلتر مجاز است.');
        /** @var Builder $query */
        $query = $model::query();
        if ($dataset === 'earnings') $query->whereNull('deleted_at');
        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? '');
            $value = trim((string) ($filter['value'] ?? ''));
            if (! isset($columns[$field]) || ! in_array($operator, ['equals','contains','gte','lte'], true) || $value === '' || mb_strlen($value) > 100) {
                throw new InvalidArgumentException('فیلتر داده نامعتبر است.');
            }
            if ($operator === 'contains') $query->where($field, 'like', '%'.addcslashes($value, '%_\\').'%');
            else $query->where($field, ['equals'=>'=','gte'=>'>=','lte'=>'<='][$operator], $value);
        }
        $count = (clone $query)->count();
        $limit = max(1, min(30, (int) ($arguments['limit'] ?? 20)));
        $rows = $query->orderByDesc('id')->limit($limit)->get(array_keys($columns))
            ->map(fn ($record) => collect($columns)->mapWithKeys(fn ($title, $field) => [$field => $record->getRawOriginal($field)])->all())->all();

        $actions = array_map(fn (array $row) => ['prompt'=>'جزئیات رکورد '.$dataset.' با شناسهٔ '.$row['id'].' را نشان بده',
            'label'=>'بررسی این مورد'], $rows);

        return ['model'=>['dataset'=>$dataset,'count'=>$count,'rows'=>$rows,'truncated'=>$count>$limit],
            'presentation'=>['type'=>'table','title'=>$label,'count'=>$count,'columns'=>$columns,
                'rows'=>$rows,'row_actions'=>$actions,'page'=>$page,'label'=>'باز کردن بخش '.$label]];
    }

    public function inspect(User $user, string $dataset, int $id): array
    {
        if (! isset(self::DATASETS[$dataset]) || ! $this->allowed($user, $dataset)) {
            throw new InvalidArgumentException('اجازهٔ مشاهدهٔ این رکورد را ندارید.');
        }
        [$label, $model, , $page, $columns] = self::DATASETS[$dataset];
        if ($page !== null && ! (new AssistantNavigationCatalog)->destination($user, $page)) $page = null;
        $record = $model::query()->find($id);
        if (! $record) throw new InvalidArgumentException('این رکورد دیگر موجود نیست.');
        $values = ['id'=>$record->getKey()];
        foreach ($columns as $field => $title) $values[$field] = $record->getRawOriginal($field);
        $relations = [];
        foreach (self::RELATIONS[$dataset] ?? [] as [$target, $targetField, $sourceField, $title]) {
            if (! $this->allowed($user, $target)) continue;
            $value = $values[$sourceField] ?? null;
            if ($value === null || $value === '') continue;
            $targetModel = self::DATASETS[$target][1];
            $count = $targetModel::query()->where($targetField, $value)->count();
            $relations[] = ['title'=>$title,'count'=>$count,'dataset'=>$target,
                'prompt'=>'رکوردهای '.$target.' را با فیلتر '.$targetField.' برابر '.$value.' نشان بده'];
        }
        $action = null;
        if ($dataset === 'patients' && filled($values['file_number'] ?? null)) {
            $action = ['page'=>'patient_profile','file_number'=>(string) $values['file_number'],'label'=>'باز کردن پرونده'];
        } elseif ($page !== null) {
            $action = ['page'=>$page,'label'=>'باز کردن بخش '.$label];
        }
        return ['model'=>['dataset'=>$dataset,'record'=>$values,'relations'=>$relations],
            'presentation'=>['type'=>'record','title'=>$label.' #'.$id,
                'fields'=>collect(['id'=>'شناسه', ...$columns])->mapWithKeys(fn ($title, $field) => [$title=>$values[$field] ?? null])->all(),
                'related_actions'=>$relations,'action'=>$action]];
    }
}
