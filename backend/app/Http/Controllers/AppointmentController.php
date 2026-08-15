<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\AppointmentBalanceAudit;
use App\Models\AppointmentNoteMessage;
use App\Models\ActivityLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryCommission;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Services\CustomerLevelService;
use App\Support\PatientPhoneVisibility;

class AppointmentController extends Controller
{
    // دریافت نوبت‌ها (فقط کل نوبت‌ها را برمی‌گرداند)
    public function getAppointments(Request $request, CustomerLevelService $levels)
    {
        $appointments = Appointment::query()
            ->orderBy('month')
            ->orderBy('day_num')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json($this->hideAppointmentPhones($appointments, $request));
        }

        $appointmentKeys = $appointments->map(fn (Appointment $appointment) => $this->appointmentNoteKey($appointment))->unique()->values();
        $noteStats = AppointmentNoteMessage::query()
            ->whereIn('appointment_key', $appointmentKeys)
            ->get()
            ->groupBy('appointment_key');
        $appointments->each(function (Appointment $appointment) use ($noteStats) {
            $messages = $noteStats->get($this->appointmentNoteKey($appointment), collect());
            $appointment->setAttribute('note_message_count', $messages->count());
            $appointment->setAttribute(
                'doctor_note_unread',
                $messages->contains(fn ($message) => $message->requires_secretary_attention && ! $message->secretary_seen_at)
            );
        });

        $fileNumbers = $appointments->pluck('file_number')->filter()->unique()->values();
        $phones = $appointments->pluck('phone')->filter()->unique()->values();

        if ($fileNumbers->isEmpty() && $phones->isEmpty()) {
            $appointments->each(fn (Appointment $appointment) =>
                $appointment->setAttribute('profile_thumbnail_url', null)
                    ->setAttribute('profile_photo_url', null)
                    ->setAttribute('has_patient_file', false)
            );

            return response()->json($appointments);
        }

        $patients = $levels->decorate(Patient::query()
            ->where(function ($query) use ($fileNumbers, $phones) {
                if ($fileNumbers->isNotEmpty()) {
                    $query->whereIn('file_number', $fileNumbers);
                }
                if ($phones->isNotEmpty()) {
                    $method = $fileNumbers->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('phone', $phones);
                }
            })
            ->get(['id', 'file_number', 'phone', 'customer_level', 'profile_photo_path', 'profile_thumbnail_path']));

        $byFileNumber = $patients->filter->file_number->keyBy('file_number');
        $byPhone = $patients->filter->phone->keyBy('phone');
        $appointments->each(function (Appointment $appointment) use ($byFileNumber, $byPhone) {
            $patient = $byFileNumber->get($appointment->file_number)
                ?? $byPhone->get($appointment->phone);
            $appointment->setAttribute('profile_thumbnail_url', $patient?->profile_thumbnail_url);
            $appointment->setAttribute('profile_photo_url', $patient?->profile_photo_url);
            $appointment->setAttribute('avatar_url', $patient?->avatar_url);
            $appointment->setAttribute('customer_level', $patient?->customer_level ?? 'silver');
            $appointment->setAttribute('has_patient_file', (bool) $patient);
            $appointment->setAttribute('patient_id', $patient?->id);
            $appointment->setAttribute('wallet_balance', $patient?->wallet_balance ?? 0);
            $appointment->setAttribute('patient_outstanding_debt', $patient?->outstanding_debt ?? 0);
        });

        return response()->json($this->hideAppointmentPhones($appointments, $request));
    }

    private function hideAppointmentPhones($appointments, Request $request)
    {
        if (PatientPhoneVisibility::canView($request)) {
            return $appointments;
        }

        return $appointments->each(function (Appointment $appointment) {
            $appointment->setAttribute('phone', PatientPhoneVisibility::mask($appointment->phone));
            $appointment->setAttribute('referrer_phone', PatientPhoneVisibility::mask($appointment->referrer_phone));
        });
    }

    private function appointmentNoteKey(Appointment $appointment): string
    {
        return implode('|', [
            $appointment->month ?? '',
            $appointment->day_num ?? '',
            $appointment->file_number ?: ($appointment->phone ?? ''),
            $appointment->time ?? '',
        ]);
    }

    // ذخیره هوشمند نوبت‌ها
    public function saveAppointments(Request $request)
    {
        $month = $request->input('month'); 
        $appointments = $request->input('appointments');

        if (is_string($appointments)) {
            $appointments = json_decode($appointments, true);
        }

        // اگر ماه ارسال نشده بود، از اولین رکورد داخل لیست سعی کن ماه را پیدا کنی
        if (!$month && !empty($appointments) && isset($appointments[0]['month'])) {
            $month = $appointments[0]['month'];
        }

        if (! $month) {
            return response()->json(['message' => 'ماه نوبت‌ها مشخص نشده است.'], 422);
        }

        // یک ردیف ممکن است به‌دلیل retry مرورگر یا دوبار کلیک در payload تکرار شود.
        // قبل از بازنویسی ماه، فقط نخستین نمونه از هر نوبت منطقی را نگه می‌داریم.
        $appointments = collect(is_array($appointments) ? $appointments : [])
            ->filter(fn ($appointment) => is_array($appointment))
            ->unique(fn (array $appointment) => $this->incomingAppointmentKey($appointment, $month))
            ->values()
            ->all();

        // ذخیره این صفحه کل ماه را بازنویسی می‌کند؛ درخواست‌های هم‌زمان نباید با هم تداخل کنند.
        $lockName = "appointments-month:{$month}";
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if (! ((int) ($lock->acquired ?? 0))) {
            return response()->json(['message' => 'ذخیره نوبت‌ها در حال انجام است؛ چند لحظه دیگر دوباره تلاش کنید.'], 423);
        }

        try {
            // 🟢 بخش حیاتی: جلوگیری از پاک شدن کل دیتابیس
            DB::transaction(function () use ($month, $appointments, $request) {
            $desiredWalletSources = [];
            $inventoryAppointmentPairs = [];
            $matchedPreviousAppointmentIds = [];
            
            if ($month) {
                // فقط و فقط نوبت‌های همین ماه پاک می‌شوند
                $existingAppointments = Appointment::query()
                    ->where('month', $month)
                    ->get();
                $existingById = $existingAppointments->keyBy('id');
                $existingByKey = $existingAppointments->keyBy(fn (Appointment $item) => $this->appointmentAuditKey($item->toArray()));

                $existingAppointments->each->delete();
                ResourceEarningLine::query()
                    ->where('month', $month)
                    ->whereNull('appointment_id')
                    ->delete();
            } else {
                // اگر اصلاً مشخص نیست چه ماهی است، هیچ کاری نکن و چیزی را پاک نکن
                return; 
            }

            if ($appointments && is_array($appointments)) {
                foreach ($appointments as $appt) {
                    $previousAppointmentId = $appt['appointment_id'] ?? $appt['id'] ?? null;
                    $previousAppointment = $previousAppointmentId
                        ? $existingById->get((int) $previousAppointmentId)
                        : null;
                    $previousAppointment ??= $existingByKey->get($this->appointmentAuditKey([
                        ...$appt,
                        'month' => $month,
                    ]));
                    // تبدیل فیلد خدمات به آرایه برای ذخیره در JSON
                    if (isset($appt['services']) && is_string($appt['services'])) {
                        $appt['services'] = json_decode($appt['services'], true);
                    }

                    if (! PatientPhoneVisibility::canView($request)) {
                        if ($previousAppointment) {
                            $appt['phone'] = $previousAppointment->phone;
                            $appt['referrer_phone'] = $previousAppointment->referrer_phone;
                        } else {
                            $appt['phone'] = '';
                            $appt['referrer_phone'] = '';
                        }
                    } elseif ($previousAppointment) {
                        if (PatientPhoneVisibility::looksMasked($appt['phone'] ?? '')) {
                            $appt['phone'] = $previousAppointment->phone;
                        }
                        if (PatientPhoneVisibility::looksMasked($appt['referrer_phone'] ?? '')) {
                            $appt['referrer_phone'] = $previousAppointment->referrer_phone;
                        }
                    }

                    $financial = $this->normalizeServiceDiscounts($appt['services'] ?? []);
                    $appt['services'] = $financial['services'];
                    if ($financial['calculated']) {
                        $appt['original_amount'] = $financial['original_amount'];
                        $appt['discount'] = $financial['discount'];
                        $appt['wallet_applied'] = min(
                            max(0, $this->signedMoneyToInteger($appt['wallet_applied'] ?? 0)),
                            max(0, $financial['original_amount'] - $financial['discount'])
                        );
                        $appt['amount'] = max(0, $financial['original_amount'] - $financial['discount'] - $appt['wallet_applied']);
                    }

                    $reward = $this->calculateReferralReward($appt);
                    $appt['referral_score'] = $reward['amount'];
                    $appt['referral_commission_type'] = $reward['type'];
                    $appt['referral_commission_value'] = $reward['value'];
                    
                    // اطمینان از اینکه فیلد ماه در دیتابیس پر می‌شود
                    $appt['month'] = $month;

                    // حذف ID قدیمی اگر وجود دارد تا رکورد جدید ساخته شود
                    unset($appt['appointment_id']);
                    unset($appt['id']);

                    $created = Appointment::create($appt);
                    $inventoryAppointmentPairs[] = ['previous' => $previousAppointment, 'current' => $created];
                    if ($previousAppointment) $matchedPreviousAppointmentIds[] = $previousAppointment->id;
                    $this->recordBalanceAudit($request, $created, $previousAppointment);
                    $this->syncResourceEarningLines($created);

                    $auditKey = $this->appointmentAuditKey($created->toArray());
                    $referralSource = "referral|{$month}|".sha1($auditKey);
                    $walletSource = "wallet-use|{$month}|".sha1($auditKey);

                    if ($reward['patient'] && $reward['amount'] > 0) {
                        $this->syncWalletTransaction(
                            $request,
                            $reward['patient'],
                            $created,
                            $referralSource,
                            'referral_reward',
                            $reward['amount'],
                            "پاداش معرفی برای {$created->lastname}",
                            [
                                'month' => $month,
                                'patient_name' => $created->lastname,
                                'patient_phone' => $created->phone,
                                'referrer_phone' => $created->referrer_phone,
                                'services' => $reward['breakdown'],
                                'commission_type' => $reward['type'],
                                'commission_value' => $reward['value'],
                                'signature' => $reward['signature'],
                            ]
                        );
                        $desiredWalletSources[] = $referralSource;
                    }

                    $requestedWallet = max(0, $this->signedMoneyToInteger($appt['wallet_applied'] ?? 0));
                    $patient = $this->appointmentPatient($created);
                    if ($patient && $requestedWallet > 0) {
                        $previousUsage = WalletTransaction::query()
                            ->where('source_key', $walletSource)
                            ->whereNull('reversed_at')
                            ->where('type', 'withdraw')
                            ->first();
                        $available = max(0, (int) $patient->fresh()->wallet_balance)
                            + ($previousUsage && (int) $previousUsage->patient_id === (int) $patient->id ? (int) $previousUsage->amount : 0);
                        $applied = min($requestedWallet, $available);
                        if ($applied > 0) {
                            $created->update(['wallet_applied' => $applied]);
                            $this->syncWalletTransaction(
                                $request,
                                $patient,
                                $created,
                                $walletSource,
                                'appointment_payment',
                                $applied,
                                "پرداخت نوبت {$created->lastname} از کیف پول",
                                [
                                    'month' => $month,
                                    'patient_name' => $created->lastname,
                                    'services' => collect($created->services)->pluck('name')->filter()->values()->all(),
                                    'signature' => sha1($applied.'|'.json_encode($created->services, JSON_UNESCAPED_UNICODE)),
                                ],
                                'withdraw'
                            );
                            $desiredWalletSources[] = $walletSource;
                        }
                    }
                }

                $this->syncMonthlySalesBonusLines($month);
            }

            $deletedAppointments = $existingAppointments
                ->reject(fn (Appointment $appointment) => in_array($appointment->id, $matchedPreviousAppointmentIds, true));
            $this->syncAppointmentInventoryStock($inventoryAppointmentPairs, $deletedAppointments);

            WalletTransaction::query()
                ->whereNull('reversed_at')
                ->where(function ($query) use ($month) {
                    $query->where('source_key', 'like', "referral|{$month}|%")
                        ->orWhere('source_key', 'like', "wallet-use|{$month}|%");
                })
                ->whereNotIn('source_key', $desiredWalletSources ?: ['__none__'])
                ->lockForUpdate()
                ->get()
                ->each(fn (WalletTransaction $transaction) => $this->reverseWalletTransaction($request, $transaction, 'حذف نوبت یا خدمت مرتبط'));
            });
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }

        return response()->json(['message' => 'تغییرات این ماه با موفقیت ثبت شد']);
    }


    public function balanceAudits(Request $request)
    {
        $query = AppointmentBalanceAudit::query()
            ->with('changedBy:id,name,email,mobile')
            ->latest('id');

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('file_number')) {
            $query->where('file_number', $request->file_number);
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        return response()->json(
            $query->limit(200)->get()->map(fn (AppointmentBalanceAudit $audit) => [
                'id' => $audit->id,
                'appointment_id' => $audit->appointment_id,
                'previous_appointment_id' => $audit->previous_appointment_id,
                'month' => $audit->month,
                'day_num' => $audit->day_num,
                'sort_order' => $audit->sort_order,
                'patient_name' => $audit->patient_name,
                'phone' => PatientPhoneVisibility::hideValue($audit->phone, $request),
                'file_number' => $audit->file_number,
                'old_debt' => (int) $audit->old_debt,
                'new_debt' => (int) $audit->new_debt,
                'changed_by_id' => $audit->changed_by_id,
                'changed_by_name' => $audit->changed_by_name ?: $audit->changedBy?->name,
                'created_at' => $audit->created_at,
            ])
        );
    }

    private function recordBalanceAudit(Request $request, Appointment $appointment, ?Appointment $previousAppointment): void
    {
        $oldDebt = $this->signedMoneyToInteger($previousAppointment?->debt);
        $newDebt = $this->signedMoneyToInteger($appointment->debt);

        if ($oldDebt === $newDebt) {
            return;
        }

        $user = $request->user();

        AppointmentBalanceAudit::create([
            'appointment_id' => $appointment->id,
            'previous_appointment_id' => $previousAppointment?->id,
            'month' => $appointment->month,
            'day_num' => $appointment->day_num,
            'sort_order' => $appointment->sort_order,
            'patient_name' => $appointment->lastname,
            'phone' => $appointment->phone,
            'file_number' => $appointment->file_number,
            'old_debt' => $oldDebt,
            'new_debt' => $newDebt,
            'changed_by_id' => $user?->id,
            'changed_by_name' => $user?->name,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    private function appointmentAuditKey(array $appointment): string
    {
        return implode('|', [
            $appointment['month'] ?? '',
            $appointment['day_num'] ?? '',
            $appointment['sort_order'] ?? '',
            $appointment['file_number'] ?? '',
            $appointment['phone'] ?? '',
            $appointment['time'] ?? '',
        ]);
    }

    private function incomingAppointmentKey(array $appointment, string $month): string
    {
        return implode('|', [
            // شناسه را وارد کلید نمی‌کنیم تا تکرارهای از قبل ثبت‌شده با
            // شناسه‌های متفاوت نیز در اولین ذخیره بعدی پاک‌سازی شوند.
            $month,
            $appointment['day_num'] ?? '',
            trim((string) ($appointment['file_number'] ?? '')),
            trim((string) ($appointment['phone'] ?? '')),
            trim((string) ($appointment['lastname'] ?? '')),
            trim((string) ($appointment['time'] ?? '')),
        ]);
    }

    private function normalizeServiceDiscounts(array $services): array
    {
        $names = collect($services)->flatMap(function ($service) {
            return collect([$service['name'] ?? null])
                ->merge(collect($service['addons'] ?? [])->pluck('name'));
        })->filter()->unique()->values();

        $prices = Inventory::query()
            ->whereIn('name', $names)
            ->pluck('amount', 'name');

        $originalAmount = 0;
        $totalDiscount = 0;
        $normalizeLine = function (array $line) use ($prices, &$originalAmount, &$totalDiscount): array {
            $quantity = max(0, (float) ($line['cc'] ?? 0));
            $lineAmount = (int) round((float) ($prices[$line['name'] ?? ''] ?? 0) * $quantity);
            $discount = min(max(0, $this->signedMoneyToInteger($line['discount'] ?? 0)), $lineAmount);
            $line['discount'] = $discount;
            $originalAmount += $lineAmount;
            $totalDiscount += $discount;

            return $line;
        };

        $normalized = collect($services)->map(function ($service) use ($normalizeLine) {
            $service = $normalizeLine(is_array($service) ? $service : []);
            $service['addons'] = collect($service['addons'] ?? [])
                ->map(fn ($addon) => $normalizeLine(is_array($addon) ? $addon : []))
                ->values()
                ->all();

            return $service;
        })->values()->all();

        return [
            'services' => $normalized,
            'calculated' => $prices->isNotEmpty(),
            'original_amount' => $originalAmount,
            'discount' => $totalDiscount,
        ];
    }
    private function signedMoneyToInteger(mixed $value): int
    {
        $text = trim(strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));

        $negative = str_starts_with($text, '-');
        $digits = preg_replace('/[^0-9]/', '', $text);
        $amount = (int) ($digits ?: 0);

        return $negative ? -$amount : $amount;
    }

    /**
     * Reconciles stock against the difference between the old and newly saved
     * appointments. Saving the scheduler rewrites a month, so applying an
     * unconditional decrement here would consume the same service repeatedly.
     */
    private function syncAppointmentInventoryStock(array $pairs, $deletedAppointments): void
    {
        $movements = [];

        foreach ($pairs as $pair) {
            $previous = $this->inventoryUsageForServices($pair['previous']?->services ?? []);
            $current = $this->inventoryUsageForServices($pair['current']?->services ?? []);
            $names = array_unique([...array_keys($previous), ...array_keys($current)]);

            foreach ($names as $name) {
                $delta = ($previous[$name] ?? 0) - ($current[$name] ?? 0);
                if (abs($delta) < 0.0001) continue;
                $appointment = $delta < 0 ? $pair['current'] : $pair['previous'];
                $movements[] = [
                    'name' => $name,
                    'quantity' => $delta,
                    'appointment' => $appointment,
                    'type' => $delta < 0 ? 'appointment_consumption' : 'appointment_reversal',
                    'description' => $delta < 0
                        ? "مصرف خدمت برای نوبت {$pair['current']->lastname} در {$pair['current']->month}/{$pair['current']->day_num}"
                        : "برگشت مصرف پس از ویرایش نوبت {$appointment?->lastname}",
                ];
            }
        }

        foreach ($deletedAppointments as $appointment) {
            foreach ($this->inventoryUsageForServices($appointment->services ?? []) as $name => $quantity) {
                $movements[] = [
                    'name' => $name,
                    'quantity' => $quantity,
                    'appointment' => $appointment,
                    'type' => 'appointment_reversal',
                    'description' => "برگشت مصرف پس از حذف نوبت {$appointment->lastname} در {$appointment->month}/{$appointment->day_num}",
                ];
            }
        }

        if ($movements === []) return;

        $totals = [];
        foreach ($movements as $movement) $totals[$movement['name']] = ($totals[$movement['name']] ?? 0) + $movement['quantity'];
        $inventories = Inventory::query()->whereIn('name', array_keys($totals))->lockForUpdate()->get()->keyBy('name');

        foreach ($totals as $name => $quantity) {
            $inventory = $inventories->get($name);
            if (! $inventory) continue;
            if ((float) $inventory->stock + $quantity < -0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inventory' => ["موجودی «{$name}» برای ثبت این خدمت کافی نیست."],
                ]);
            }
        }

        foreach ($totals as $name => $quantity) {
            $inventory = $inventories->get($name);
            if (! $inventory) continue;
            $inventory->update(['stock' => max(0, (float) $inventory->stock + $quantity)]);
        }

        foreach ($movements as $movement) {
            $inventory = $inventories->get($movement['name']);
            if (! $inventory) continue;
            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'inventory_name' => $inventory->name,
                'quantity' => $movement['quantity'],
                'type' => $movement['type'],
                'appointment_id' => $movement['type'] === 'appointment_consumption' ? $movement['appointment']?->id : null,
                'description' => $movement['description'],
                'occurred_at' => now(),
            ]);
        }
    }

    private function inventoryUsageForServices(array $services): array
    {
        $usage = [];
        foreach ($this->appointmentServiceLines($services) as $line) {
            $name = trim((string) ($line['name'] ?? ''));
            $quantity = max(0, (float) ($line['quantity'] ?? 0));
            if ($name === '' || $quantity <= 0) continue;
            $usage[$name] = ($usage[$name] ?? 0) + $quantity;
        }
        return $usage;
    }

    private function syncResourceEarningLines(Appointment $appointment): void
    {
        ResourceEarningLine::query()->where('appointment_id', $appointment->id)->delete();

        $lines = $this->appointmentServiceLines($appointment->services ?? []);
        if ($lines === []) {
            return;
        }

        $names = collect($lines)->pluck('name')->filter()->unique()->values();
        $inventories = Inventory::with('commissions')->whereIn('name', $names)->get()->keyBy('name');
        $doctors = Doctor::query()->get()->keyBy(fn (Doctor $doctor) => $this->resourceKey($doctor->name));
        $staff = Staff::query()->get()->keyBy(fn (Staff $item) => $this->resourceKey($item->name));

        foreach ($lines as $line) {
            $inventory = $inventories->get($line['name']);
            if (! $inventory) {
                continue;
            }

            foreach ([
                ['type' => 'doctor', 'name' => $line['doctor'] ?: $appointment->doctor, 'map' => $doctors],
                ['type' => 'staff', 'name' => $line['consultant'] ?: $appointment->consultant, 'map' => $staff],
            ] as $target) {
                $resourceName = trim((string) $target['name']);
                if ($resourceName === '') {
                    continue;
                }

                $resource = $target['map']->get($this->resourceKey($resourceName));
                if (! $resource || ! $this->resourceReceivesCommission($resource, (bool) $appointment->new_customer)) {
                    continue;
                }

                $this->createBaseCommissionLine($appointment, $line, $inventory, $target['type'], $resource);
                $this->createInventoryCommissionLines($appointment, $line, $inventory, $target['type'], $resource);
            }
        }
    }

    private function appointmentServiceLines(array $services): array
    {
        $lines = [];
        foreach (array_values($services) as $index => $service) {
            $service = is_array($service) ? $service : [];
            $lines[] = $this->normalizeEarningServiceLine($service, $index, false);
            foreach (array_values($service['addons'] ?? []) as $addon) {
                $lines[] = $this->normalizeEarningServiceLine(is_array($addon) ? $addon : [], $index, true, $service);
            }
        }

        return collect($lines)->filter(fn ($line) => $line['name'] !== '')->values()->all();
    }

    private function normalizeEarningServiceLine(array $line, int $index, bool $isAddon, array $parent = []): array
    {
        return [
            'name' => trim((string) ($line['name'] ?? '')),
            'quantity' => max(1, (float) ($line['cc'] ?? 1)),
            'discount' => max(0, (float) ($line['discount'] ?? 0)),
            'doctor' => trim((string) ($line['doctor'] ?? $parent['doctor'] ?? '')),
            'consultant' => trim((string) ($line['consultant'] ?? $parent['consultant'] ?? '')),
            'index' => $index,
            'is_addon' => $isAddon,
        ];
    }

    private function createBaseCommissionLine(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource): void
    {
        $amounts = $this->earningAmounts($line, $inventory, (bool) $resource->commission_after_materials);
        $commissionValue = (float) ($resource->bonus ?? 0);
        $amount = $this->calculateCommissionAmount('percent', $commissionValue, $amounts['base'], $line['quantity']);
        if ($amount <= 0) {
            return;
        }

        ResourceEarningLine::create($this->earningPayload(
            $appointment,
            $line,
            $inventory,
            $type,
            $resource,
            'base_commission',
            'percent',
            $commissionValue,
            $amount,
            $amounts,
            "پورسانت پایه {$commissionValue}% برای {$line['name']}"
        ));
    }

    private function createInventoryCommissionLines(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource): void
    {
        $commissions = $inventory->commissions
            ->where('recipient_type', $type)
            ->filter(fn (InventoryCommission $commission) => $this->commissionMatchesResource($commission, $resource));

        foreach ($commissions as $commission) {
            $amounts = $this->earningAmounts($line, $inventory, false);
            $commissionType = $commission->commission_type === 'fixed' ? 'fixed' : 'percent';
            $commissionValue = (float) $commission->commission_value;
            $amount = $this->calculateCommissionAmount($commissionType, $commissionValue, $amounts['net'], $line['quantity']);
            if ($amount <= 0) {
                continue;
            }

            ResourceEarningLine::create($this->earningPayload(
                $appointment,
                $line,
                $inventory,
                $type,
                $resource,
                'inventory_commission',
                $commissionType,
                $commissionValue,
                $amount,
                [...$amounts, 'base' => $amounts['net']],
                "پورسانت اختصاصی انبار {$commissionValue}".($commissionType === 'percent' ? '%' : ' تومان')." برای {$line['name']}"
            ));
        }
    }

    private function earningPayload(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource, string $earningType, string $commissionType, float $commissionValue, float $amount, array $amounts, string $description): array
    {
        return [
            'appointment_id' => $appointment->id,
            'month' => $appointment->month,
            'day_num' => $appointment->day_num,
            'earned_at' => $appointment->created_at,
            'resource_type' => $type,
            'resource_id' => $resource->id,
            'resource_name' => $resource->name,
            'earning_type' => $earningType,
            'inventory_id' => $inventory->id,
            'inventory_name' => $inventory->name,
            'service_name' => $line['name'],
            'service_line_index' => $line['index'],
            'is_addon' => $line['is_addon'],
            'quantity' => $line['quantity'],
            'gross_amount' => $amounts['gross'],
            'discount_amount' => $amounts['discount'],
            'net_amount' => $amounts['net'],
            'material_cost' => $amounts['materials'],
            'commission_base' => $amounts['base'],
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
            'amount' => round($amount),
            'commission_after_materials' => (bool) ($resource->commission_after_materials ?? false),
            'commission_customer_scope' => $resource->commission_customer_scope ?? 'both',
            'appointment_new_customer' => (bool) $appointment->new_customer,
            'calculation_snapshot' => [
                'resource_bonus' => (float) ($resource->bonus ?? 0),
                'resource_sales_bonus_enabled' => (bool) ($resource->sales_bonus_enabled ?? false),
                'resource_sales_bonus_tiers' => $resource->sales_bonus_tiers ?? [],
                'inventory_amount' => (float) ($inventory->amount ?? 0),
                'inventory_material_price' => (float) ($inventory->price ?? 0),
                'inventory_default_commission_type' => $inventory->default_commission_type,
                'inventory_default_commission_value' => (float) $inventory->default_commission_value,
            ],
            'description' => $description,
        ];
    }

    private function earningAmounts(array $line, Inventory $inventory, bool $afterMaterials): array
    {
        $gross = (float) ($inventory->amount ?? 0) * $line['quantity'];
        $discount = min($line['discount'], $gross);
        $net = max(0, $gross - $discount);
        $materials = (float) ($inventory->price ?? 0) * $line['quantity'];
        $base = $afterMaterials ? max(0, $net - $materials) : $net;

        return compact('gross', 'discount', 'net', 'materials', 'base');
    }

    private function calculateCommissionAmount(string $type, float $value, float $base, float $quantity): float
    {
        return $type === 'fixed'
            ? max(0, $value) * max(1, $quantity)
            : max(0, $base) * max(0, $value) / 100;
    }

    private function commissionMatchesResource(InventoryCommission $commission, Doctor|Staff $resource): bool
    {
        if ($commission->recipient_id && (int) $commission->recipient_id === (int) $resource->id) {
            return true;
        }

        return $this->resourceKey($commission->recipient_name) === $this->resourceKey($resource->name);
    }

    private function resourceReceivesCommission(Doctor|Staff $resource, bool $newCustomer): bool
    {
        return match ($resource->commission_customer_scope ?? 'both') {
            'new' => $newCustomer,
            'existing' => ! $newCustomer,
            default => true,
        };
    }

    private function syncMonthlySalesBonusLines(string $month): void
    {
        foreach ([['type' => 'doctor', 'model' => Doctor::class], ['type' => 'staff', 'model' => Staff::class]] as $target) {
            $target['model']::query()->where('sales_bonus_enabled', true)->get()->each(function (Doctor|Staff $resource) use ($month, $target) {
                $sales = (float) ResourceEarningLine::query()
                    ->where('month', $month)
                    ->where('resource_type', $target['type'])
                    ->where('resource_id', $resource->id)
                    ->whereIn('earning_type', ['base_commission', 'inventory_commission'])
                    ->sum('net_amount');

                $tier = collect($resource->sales_bonus_tiers ?? [])
                    ->filter(fn ($item) => $sales >= (float) ($item['sales_from'] ?? 0))
                    ->sortByDesc(fn ($item) => (float) ($item['sales_from'] ?? 0))
                    ->first();

                if (! $tier) {
                    return;
                }

                ResourceEarningLine::create([
                    'appointment_id' => null,
                    'month' => $month,
                    'earned_at' => now(),
                    'resource_type' => $target['type'],
                    'resource_id' => $resource->id,
                    'resource_name' => $resource->name,
                    'earning_type' => 'sales_bonus',
                    'gross_amount' => $sales,
                    'net_amount' => $sales,
                    'commission_base' => $sales,
                    'commission_type' => 'tier',
                    'commission_value' => (float) ($tier['sales_from'] ?? 0),
                    'amount' => (float) ($tier['salary_addition'] ?? 0),
                    'commission_after_materials' => (bool) ($resource->commission_after_materials ?? false),
                    'commission_customer_scope' => $resource->commission_customer_scope ?? 'both',
                    'calculation_snapshot' => [
                        'monthly_sales' => $sales,
                        'matched_tier' => $tier,
                        'all_tiers' => $resource->sales_bonus_tiers ?? [],
                    ],
                    'description' => 'پاداش پلکانی فروش ماهانه',
                ]);
            });
        }
    }

    private function resourceKey(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    private function calculateReferralReward(array $appointment): array
    {
        $phone = trim((string) ($appointment['referrer_phone'] ?? ''));
        $referrer = $phone !== '' ? Patient::query()->where('phone', $phone)->first() : null;
        $services = collect($appointment['services'] ?? []);
        $names = $services->flatMap(fn ($service) => collect([$service['name'] ?? null])
            ->merge(collect($service['addons'] ?? [])->pluck('name')))->filter()->unique()->values();
        $inventory = Inventory::query()->whereIn('name', $names)->get()->keyBy('name');
        $breakdown = [];
        $total = 0;
        $types = [];
        $values = [];

        $calculate = function (string $name, float $quantity = 1) use ($inventory, &$breakdown, &$total, &$types, &$values) {
            $item = $inventory->get($name);
            if (! $item) return;
            $type = $item->default_commission_type === 'fixed' ? 'fixed' : 'percent';
            $value = (float) $item->default_commission_value;
            $base = (float) ($item->amount ?? 0) * max($quantity, 1);
            $reward = $type === 'fixed' ? $value * max($quantity, 1) : ($base * $value / 100);
            $reward = (int) round(max(0, $reward));
            $total += $reward;
            $types[] = $type;
            $values[] = $value;
            $breakdown[] = [
                'service' => $name, 'quantity' => max($quantity, 1), 'service_amount' => $base,
                'commission_type' => $type, 'commission_value' => $value, 'reward_amount' => $reward,
            ];
        };

        foreach ($services as $service) {
            $calculate((string) ($service['name'] ?? ''), (float) ($service['cc'] ?? 1));
            foreach (($service['addons'] ?? []) as $addon) {
                $calculate((string) ($addon['name'] ?? ''), (float) ($addon['cc'] ?? 1));
            }
        }

        $uniqueTypes = array_values(array_unique($types));
        $uniqueValues = array_values(array_unique($values));
        return [
            'patient' => $referrer,
            'amount' => $referrer ? $total : 0,
            'type' => count($uniqueTypes) === 1 ? $uniqueTypes[0] : (count($uniqueTypes) ? 'mixed' : null),
            'value' => count($uniqueValues) === 1 ? $uniqueValues[0] : 0,
            'breakdown' => $breakdown,
            'signature' => sha1(json_encode([$phone, $breakdown], JSON_UNESCAPED_UNICODE)),
        ];
    }

    private function appointmentPatient(Appointment $appointment): ?Patient
    {
        return Patient::query()->where(function ($query) use ($appointment) {
            if ($appointment->file_number) $query->where('file_number', $appointment->file_number);
            if ($appointment->phone) {
                $appointment->file_number ? $query->orWhere('phone', $appointment->phone) : $query->where('phone', $appointment->phone);
            }
        })->first();
    }

    private function syncWalletTransaction(Request $request, Patient $patient, Appointment $appointment, string $sourceKey, string $sourceType, int $amount, string $description, array $metadata, string $type = 'deposit'): void
    {
        $existing = WalletTransaction::query()->where('source_key', $sourceKey)->whereNull('reversed_at')->lockForUpdate()->first();
        if ($existing && (int) $existing->patient_id === (int) $patient->id && (int) $existing->amount === $amount
            && data_get($existing->metadata, 'signature') === ($metadata['signature'] ?? null)) {
            $existing->update(['appointment_id' => $appointment->id]);
            return;
        }
        if ($existing) $this->reverseWalletTransaction($request, $existing, 'اصلاح نوبت یا خدمات');

        WalletTransaction::create([
            'patient_id' => $patient->id, 'type' => $type, 'amount' => $amount,
            'description' => $description, 'source_type' => $sourceType, 'source_key' => $sourceKey,
            'appointment_id' => $appointment->id, 'created_by' => $request->user()?->id, 'metadata' => $metadata,
        ]);
    }

    private function reverseWalletTransaction(Request $request, WalletTransaction $transaction, string $reason): void
    {
        if ($transaction->reversed_at) return;
        $reverse = WalletTransaction::create([
            'patient_id' => $transaction->patient_id,
            'type' => $transaction->type === 'deposit' ? 'withdraw' : 'deposit',
            'amount' => $transaction->amount,
            'description' => "برگشت: {$transaction->description}",
            'source_type' => 'reversal',
            'source_key' => $transaction->source_key.'|reversal|'.now()->format('YmdHisv'),
            'appointment_id' => $transaction->appointment_id,
            'reversed_transaction_id' => $transaction->id,
            'created_by' => $request->user()?->id,
            'metadata' => ['reason' => $reason, 'original' => $transaction->metadata],
        ]);
        $transaction->update(['reversed_at' => now(), 'reversed_transaction_id' => $reverse->id]);
    }

    public function patientHistory(Request $request)
    {
        $query = Appointment::query();

        if ($request->filled('file_number')) {
            $query->where('file_number', $request->file_number);
        }

        if ($request->filled('phone')) {
            $query->orWhere('phone', $request->phone);
        }

        $columns = [
            'id',
            'month',
            'day_num',
            'lastname',
            'phone',
            'file_number',
            'time',
            'status',
            'arrived_at',
            'done',
            'completed_at',
            'source',
            'services',
            'amount',
            'debt',
            'payment_method',
            'payment_account',
            'payment_details',
            'payment_link',
            'payment_link_sent_count',
            'payment_link_last_sent_at',
            'referrer_phone',
            'referral_score',
            'discount',
            'original_amount',
            'description',
            'doctor_note',
            'created_at',
        ];

        if ($request->filled('per_page')) {
            $perPage = min(50, max(5, (int) $request->integer('per_page', 15)));
            $page = max(1, (int) $request->integer('page', 1));
            $total = (clone $query)->count();
            $items = $query
                ->orderByDesc('created_at')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get($columns);

            $items = $this->withPatientHistoryRegistrationMeta($items);

            return response()->json([
                'data' => $this->hideAppointmentPhones($items, $request),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => ($page * $perPage) < $total,
            ]);
        }

        $items = $query
            ->orderByDesc('created_at')
            ->get($columns);

        $items = $this->withPatientHistoryRegistrationMeta($items);

        return response()->json($this->hideAppointmentPhones($items, $request));
    }

    /**
     * اطلاعات ثبت اولیهٔ نوبت را فقط برای راهنمای hover سوابق برمی‌گرداند.
     */
    private function withPatientHistoryRegistrationMeta($appointments)
    {
        $appointmentIds = $appointments->pluck('id')->filter()->values();
        if ($appointmentIds->isEmpty()) {
            return $appointments;
        }

        $creationLogs = ActivityLog::query()
            ->where('subject_type', Appointment::class)
            ->where('event', 'created')
            ->whereIn('subject_id', $appointmentIds)
            ->orderBy('id')
            ->get(['subject_id', 'user_name', 'created_at'])
            ->unique('subject_id')
            ->keyBy('subject_id');

        return $appointments->each(function (Appointment $appointment) use ($creationLogs) {
            $log = $creationLogs->get($appointment->id);
            $appointment->setAttribute('registered_by', $log?->user_name ?: null);
            $appointment->setAttribute('registered_at', $log?->created_at?->toDateTimeString() ?: $appointment->created_at?->toDateTimeString());
        });
    }
}
