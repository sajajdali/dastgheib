<?php

namespace App\Http\Controllers;

use App\Events\AppointmentChanged;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\AppointmentBalanceAudit;
use App\Models\AppointmentNoteMessage;
use App\Models\ActivityLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Inventory;
use App\Models\InventoryAddonDefinition;
use App\Models\ServiceFollowup;
use App\Models\HiddenAppointmentDay;
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
    // دریافت نوبت‌های واقعی؛ اسلات‌های خالی از تنظیمات ساعات کاری در فرانت ساخته می‌شوند.
    public function getAppointments(Request $request, CustomerLevelService $levels)
    {
        $month = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])$/'],
        ])['month'] ?? null;

        $appointments = Appointment::query()
            ->when($month, fn ($query) => $query->where('month', $month))
            ->where(function ($query) {
                $query->whereNotNull('lastname')->where('lastname', '<>', '')
                    ->orWhere(function ($query) {
                        $query->whereNotNull('file_number')->where('file_number', '<>', '');
                    })
                    ->orWhere(function ($query) {
                        $query->whereNotNull('phone')->where('phone', '<>', '');
                    });
            })
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

    public function hiddenDays(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'string', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/']]);

        return response()->json(HiddenAppointmentDay::query()
            ->where('month', $data['month'])
            ->orderBy('day_num')
            ->pluck('day_num')
            ->map(fn ($day) => (int) $day)
            ->values());
    }

    public function hideDay(Request $request)
    {
        $data = $this->validateScheduleDay($request);
        HiddenAppointmentDay::query()->firstOrCreate($data);

        return response()->json(['message' => 'روز از برنامه پنهان شد.']);
    }

    public function restoreDay(Request $request)
    {
        $data = $this->validateScheduleDay($request);
        $restored = HiddenAppointmentDay::query()->where($data)->delete() > 0;

        return response()->json(['restored' => $restored]);
    }

    private function validateScheduleDay(Request $request): array
    {
        return $request->validate([
            'month' => ['required', 'string', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/'],
            'day_num' => ['required', 'integer', 'between:1,31'],
        ]);
    }

    private function hideAppointmentPhones($appointments, Request $request)
    {
        if (PatientPhoneVisibility::canView($request)) {
            return $appointments;
        }

        return $appointments->each(function (Appointment $appointment) {
            $appointment->setAttribute('phone', '');
            $appointment->setAttribute('referrer_phone', '');
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
        return $this->upsertAppointmentsSafely($request);

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
                // روزهای پنهان‌شده در نوبت‌دهی، حذف نرم هستند: رکوردهای آن‌ها
                // در بازنویسی ماه دست‌نخورده می‌مانند تا با بازگرداندن روز دوباره نمایش داده شوند.
                $hiddenDayNumbers = HiddenAppointmentDay::query()
                    ->where('month', $month)
                    ->pluck('day_num')
                    ->map(fn ($day) => (int) $day)
                    ->all();
                $hiddenAppointments = $hiddenDayNumbers
                    ? Appointment::query()
                        ->where('month', $month)
                        ->whereIn('day_num', $hiddenDayNumbers)
                        ->get()
                    : collect();
                foreach ($hiddenAppointments as $hiddenAppointment) {
                    $auditKey = $this->appointmentAuditKey($hiddenAppointment->toArray());
                    $desiredWalletSources[] = "referral|{$month}|".sha1($auditKey);
                    $desiredWalletSources[] = "wallet-use|{$month}|".sha1($auditKey);
                }

                // فقط و فقط نوبت‌های قابل‌نمایش همین ماه بازنویسی می‌شوند.
                $existingAppointments = Appointment::query()
                    ->where('month', $month)
                    ->when($hiddenDayNumbers, fn ($query) => $query->whereNotIn('day_num', $hiddenDayNumbers))
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

                    // این فیلد فقط برای تصمیم‌گیری دربارهٔ ثبت پیگیری دوره‌ای استفاده می‌شود
                    // و ستونی در جدول نوبت‌ها ندارد.
                    $followupConfirmed = filter_var($appt['followup_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    unset($appt['followup_confirmed']);

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
                            max(0, $financial['original_amount'] + $financial['surcharge'] - $financial['discount'])
                        );
                        $appt['amount'] = max(0, $financial['original_amount'] + $financial['surcharge'] - $financial['discount'] - $appt['wallet_applied']);
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
                    if ($followupConfirmed) {
                        $this->syncServiceFollowups($created);
                    }

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

    /**
     * The scheduler used to treat a browser payload as the complete truth for a
     * month.  A stale/empty payload could therefore delete unrelated records.
     * This endpoint is deliberately an upsert-only operation: omission is
     * never interpreted as deletion.  Deletion has its own explicit endpoint.
     */
    private function upsertAppointmentsSafely(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'string', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])$/'],
            'appointments' => ['required', 'array', 'min:1'],
            'appointments.*.appointment_id' => ['nullable', 'integer'],
            'appointments.*.lock_version' => ['nullable', 'integer', 'min:1'],
        ]);
        // Laravel returns only nested keys which have explicit validation
        // rules. Using that result directly used to discard every appointment
        // field except id/version. Keep the submitted rows, then whitelist the
        // writable columns below before filling the model.
        $data = [
            'month' => $validated['month'],
            'appointments' => $request->input('appointments', []),
        ];

        $saved = DB::transaction(function () use ($data, $request) {
            $saved = [];
            foreach ($data['appointments'] as $incoming) {
                if (! is_array($incoming)) continue;
                $id = $incoming['appointment_id'] ?? $incoming['id'] ?? null;
                $previous = $id ? Appointment::query()->lockForUpdate()->find($id) : null;
                $before = null;

                if ($id && ! $previous) {
                    abort(409, 'این نوبت دیگر وجود ندارد. لطفاً صفحه را تازه‌سازی کنید.');
                }
                if ($previous && $previous->month !== $data['month']) {
                    abort(422, 'نوبت ارسالی متعلق به ماه انتخاب‌شده نیست.');
                }
                // A delayed browser request containing an empty slot must
                // never clear a booking which has already been persisted.
                if (
                    $previous
                    && trim((string) $previous->lastname) !== ''
                    && trim((string) ($incoming['lastname'] ?? '')) === ''
                ) {
                    $saved[] = $previous->fresh();
                    continue;
                }
                // The client currently sends the visible month as a payload.
                // Never write a row which is already identical: doing so would
                // increase its version merely by opening/using another row.
                if ($previous && $this->incomingAppointmentMatches($incoming, $previous)) {
                    $saved[] = $previous->fresh();
                    continue;
                }
                $currentLockVersion = $previous ? max(1, (int) $previous->lock_version) : null;
                if ($previous && (int) ($incoming['lock_version'] ?? 0) !== $currentLockVersion) {
                    // The current screen submits a month payload. A stale
                    // version on an *unchanged* row must not reject a real
                    // edit to another row (nor turn merely opening the page
                    // into a conflict). Only a materially changed stale row
                    // is a genuine concurrent-edit conflict.
                    if ($this->incomingAppointmentMatches($incoming, $previous)) {
                        $saved[] = $previous->fresh();
                        continue;
                    }
                    abort(409, 'این نوبت در دستگاه یا مرورگر دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.');
                }

                $followupConfirmed = filter_var($incoming['followup_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                unset($incoming['id'], $incoming['appointment_id'], $incoming['lock_version'], $incoming['followup_confirmed'], $incoming['_client_key']);
                $incoming = collect($incoming)->only([
                    'day_num', 'sort_order', 'lastname', 'gender', 'phone',
                    'file_number', 'time', 'status', 'arrived_at', 'wait_minutes',
                    'doctor', 'consultant', 'source', 'campaign_id', 'description',
                    'doctor_note', 'done', 'completed_at', 'amount',
                    'original_amount', 'debt', 'payment_method', 'payment_account',
                    'payment_details', 'payment_link', 'payment_link_sent_count',
                    'payment_link_last_sent_at', 'referrer_phone', 'referral_score',
                    'wallet_applied', 'referral_commission_type',
                    'referral_commission_value', 'discount', 'new_customer',
                    'appointment_sms', 'info_sms', 'completion_sms_statuses',
                    'services', 'service_types',
                ])->all();
                $incoming['month'] = $data['month'];
                if (isset($incoming['services']) && is_string($incoming['services'])) {
                    $incoming['services'] = json_decode($incoming['services'], true) ?: [];
                }

                if (! PatientPhoneVisibility::canView($request) && $previous) {
                    $incoming['phone'] = $previous->phone;
                    $incoming['referrer_phone'] = $previous->referrer_phone;
                }
                $financial = $this->normalizeServiceDiscounts($incoming['services'] ?? []);
                $incoming['services'] = $financial['services'];
                if ($financial['calculated']) {
                    $incoming['original_amount'] = $financial['original_amount'];
                    $incoming['discount'] = $financial['discount'];
                    $incoming['wallet_applied'] = min(max(0, $this->signedMoneyToInteger($incoming['wallet_applied'] ?? 0)), max(0, $financial['original_amount'] + $financial['surcharge'] - $financial['discount']));
                    $incoming['amount'] = max(0, $financial['original_amount'] + $financial['surcharge'] - $financial['discount'] - $incoming['wallet_applied']);
                }
                $incoming = $this->normalizeAppointmentTracking($incoming);
                $reward = $this->calculateReferralReward($incoming);
                $incoming['referral_score'] = $reward['amount'];
                $incoming['referral_commission_type'] = $reward['type'];
                $incoming['referral_commission_value'] = $reward['value'];

                if ($previous) {
                    $before = clone $previous;
                    $previous->fill($incoming);
                    $previous->lock_version = $currentLockVersion + 1;
                    $previous->save();
                    $appointment = $previous;
                    $this->recordBalanceAudit($request, $appointment, $before);
                    $this->syncAppointmentInventoryStock([['previous' => $before, 'current' => $appointment]], collect());
                } else {
                    $incoming['lock_version'] = 1;
                    $appointment = Appointment::create($incoming);
                    $this->recordBalanceAudit($request, $appointment, null);
                    $this->syncAppointmentInventoryStock([['previous' => null, 'current' => $appointment]], collect());
                }
                $this->syncResourceEarningLines($appointment);
                $this->syncAppointmentWalletEffects($request, $appointment, $reward, $before ?? null);
                if ($followupConfirmed) $this->syncServiceFollowups($appointment);
                $saved[] = $appointment->fresh();
            }
            $this->syncMonthlySalesBonusLines($data['month']);
            return $saved;
        });

        foreach ($saved as $appointment) {
            $this->broadcastAppointmentChange($appointment);
        }

        return response()->json(['message' => 'نوبت‌های تغییرکرده ثبت شدند.', 'appointments' => $saved]);
    }

    /**
     * Persist the identity/time fields of one table row synchronously.
     * Direct table entry must not share the debounced month-save queue.
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'month' => ['required', 'string', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/'],
            'day_num' => ['required', 'integer', 'between:1,31'],
            'time' => ['required', 'date_format:H:i'],
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());
        [$before, $updated] = DB::transaction(function () use ($appointment, $data) {
            $current = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_if((int) $current->lock_version !== (int) $data['lock_version'], 409,
                'این نوبت تغییر کرده است. صفحه را تازه‌سازی کنید و دوباره انتقال دهید.');
            $before = clone $current;
            $current->update([
                'month' => $data['month'],
                'day_num' => $data['day_num'],
                'time' => $data['time'],
                'status' => 'انتقال داده شده',
                'lock_version' => (int) $current->lock_version + 1,
            ]);
            return [$before, $current];
        });
        // Notify both calendars when moving across months, on this tenant's private channel.
        if ($before->month !== $updated->month) {
            $this->broadcastAppointmentChange($before, 'rescheduled');
        }
        $this->broadcastAppointmentChange($updated, 'rescheduled');
        return response()->json(['appointment_id' => $updated->id, 'lock_version' => $updated->lock_version]);
    }

    private function appointmentValidationMessages(): array
    {
        return [
            'required' => 'وارد کردن :attribute الزامی است.',
            'string' => ':attribute باید به‌صورت متن وارد شود.',
            'integer' => ':attribute باید عدد صحیح باشد.',
            'min' => ':attribute باید حداقل :min باشد.',
            'max' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
            'between' => ':attribute باید بین :min و :max باشد.',
            'regex' => 'قالب :attribute معتبر نیست.',
            'date_format' => ':attribute را به‌صورت ساعت و دقیقه وارد کنید (مثلاً 09:30).',
        ];
    }

    private function appointmentValidationAttributes(): array
    {
        return [
            'appointment_id' => 'شناسه نوبت', 'lock_version' => 'نسخه نوبت',
            'month' => 'ماه نوبت', 'day_num' => 'روز نوبت', 'sort_order' => 'ترتیب نوبت',
            'lastname' => 'نام و نام خانوادگی', 'time' => 'ساعت نوبت',
            'phone' => 'شماره موبایل', 'gender' => 'جنسیت', 'doctor' => 'پزشک',
            'consultant' => 'مشاور', 'source' => 'نحوه آشنایی', 'description' => 'توضیحات',
        ];
    }

    public function saveRow(Request $request)
    {
        $request->validate([
            'appointment_id' => ['nullable', 'integer'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'month' => ['required', 'string', 'regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])$/'],
            'day_num' => ['required', 'integer', 'between:1,31'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'lastname' => ['required', 'string', 'max:255'],
            'time' => ['required', 'date_format:H:i'],
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());

        $row = $request->except('appointments');
        $request->merge(['appointments' => [$row]]);
        $response = $this->upsertAppointmentsSafely($request);
        $result = $response->getData(true);
        $appointment = $result['appointments'][0] ?? null;

        return response()->json([
            'message' => 'نوبت در دیتابیس ثبت شد.',
            'appointment' => $appointment,
        ]);
    }

    /** Keep arrival, completion and the resulting patient waiting time authoritative on the server. */
    private function normalizeAppointmentTracking(array $appointment): array
    {
        $arrived = trim((string) ($appointment['status'] ?? '')) === 'آمد';
        $completed = trim((string) ($appointment['done'] ?? '')) === 'انجام شد';

        if ($arrived && empty($appointment['arrived_at'])) {
            $appointment['arrived_at'] = now()->toDateTimeString();
        }
        if (! $arrived) {
            $appointment['arrived_at'] = null;
        }
        if ($completed && empty($appointment['completed_at'])) {
            $appointment['completed_at'] = now()->toDateTimeString();
        }
        if (! $completed) {
            $appointment['completed_at'] = null;
        }

        $appointment['wait_minutes'] = null;
        if (! empty($appointment['arrived_at']) && ! empty($appointment['completed_at'])) {
            try {
                $arrival = now()->parse($appointment['arrived_at']);
                $completion = now()->parse($appointment['completed_at']);
                if (! $completion->isBefore($arrival)) {
                    $appointment['wait_minutes'] = $arrival->diffInMinutes($completion);
                }
            } catch (\Throwable) {
                // Invalid legacy values are kept from breaking an appointment save.
            }
        }

        return $appointment;
    }

    private function incomingAppointmentMatches(array $incoming, Appointment $existing): bool
    {
        $fields = [
            'day_num', 'sort_order', 'lastname', 'gender', 'phone', 'file_number',
            'time', 'status', 'arrived_at', 'wait_minutes', 'doctor', 'consultant',
            'source', 'campaign_id', 'description', 'doctor_note', 'done',
            'completed_at', 'amount', 'original_amount', 'debt', 'payment_method',
            'payment_account', 'payment_details', 'payment_link',
            'payment_link_sent_count', 'payment_link_last_sent_at', 'referrer_phone',
            'referral_score', 'wallet_applied', 'referral_commission_type',
            'referral_commission_value', 'discount', 'new_customer',
            'appointment_sms', 'info_sms', 'completion_sms_statuses',
            'service_types', 'services',
        ];

        foreach ($fields as $field) {
            if ($this->canonicalAppointmentValue($incoming[$field] ?? null) !== $this->canonicalAppointmentValue($existing->getAttribute($field))) {
                return false;
            }
        }

        return true;
    }

    private function canonicalAppointmentValue(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : trim($value);
        }
        if (is_array($value)) {
            $value = $this->canonicalizeAppointmentArray($value);
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_bool($value)) return $value ? '1' : '0';
        return trim((string) ($value ?? ''));
    }

    private function canonicalizeAppointmentArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->canonicalizeAppointmentArray($item);
            elseif (is_string($item)) $value[$key] = trim($item);
        }
        if (! array_is_list($value)) ksort($value);
        return $value;
    }

    /** Wallet source keys are based on the immutable appointment id, not mutable fields such as time/phone. */
    private function syncAppointmentWalletEffects(Request $request, Appointment $appointment, array $reward, ?Appointment $legacyAppointment = null): void
    {
        $referralKey = "appointment-referral|{$appointment->id}";
        $walletKey = "appointment-wallet|{$appointment->id}";
        // Transactions written by the old replace-all scheduler used a mutable
        // month/key.  Convert their effect on first safe edit so rewards are
        // never paid twice during the rollout.
        if ($legacyAppointment) {
            $legacyKey = sha1($this->appointmentAuditKey($legacyAppointment->toArray()));
            $this->reverseAppointmentWalletSourceUnless($request, "referral|{$legacyAppointment->month}|{$legacyKey}", false);
            $this->reverseAppointmentWalletSourceUnless($request, "wallet-use|{$legacyAppointment->month}|{$legacyKey}", false);
        }
        $this->reverseAppointmentWalletSourceUnless($request, $referralKey, $reward['patient'] && $reward['amount'] > 0);
        if ($reward['patient'] && $reward['amount'] > 0) {
            $this->syncWalletTransaction($request, $reward['patient'], $appointment, $referralKey, 'referral_reward', $reward['amount'], "پاداش معرفی برای {$appointment->lastname}", ['month' => $appointment->month, 'signature' => $reward['signature']]);
        }

        $requested = max(0, $this->signedMoneyToInteger($appointment->wallet_applied));
        $patient = $this->appointmentPatient($appointment);
        $existing = WalletTransaction::query()->where('source_key', $walletKey)->whereNull('reversed_at')->first();
        $available = $patient ? max(0, (int) $patient->fresh()->wallet_balance) + ($existing && (int) $existing->patient_id === (int) $patient->id ? (int) $existing->amount : 0) : 0;
        $applied = $patient ? min($requested, $available) : 0;
        $this->reverseAppointmentWalletSourceUnless($request, $walletKey, $patient && $applied > 0);
        if ($patient && $applied > 0) {
            if ((int) $appointment->wallet_applied !== $applied) $appointment->update(['wallet_applied' => $applied]);
            $this->syncWalletTransaction($request, $patient, $appointment, $walletKey, 'appointment_payment', $applied, "پرداخت نوبت {$appointment->lastname} از کیف پول", ['month' => $appointment->month], 'withdraw');
        }
    }

    private function reverseAppointmentWalletSourceUnless(Request $request, string $sourceKey, bool $keep): void
    {
        if ($keep) return;
        WalletTransaction::query()->where('source_key', $sourceKey)->whereNull('reversed_at')->lockForUpdate()->get()
            ->each(fn (WalletTransaction $transaction) => $this->reverseWalletTransaction($request, $transaction, 'اصلاح یا حذف نوبت'));
    }

    /** Explicit deletion; a missing row in a browser payload can never delete data. */
    public function destroy(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        if ((int) $appointment->lock_version !== (int) $data['lock_version']) {
            return response()->json(['message' => 'این نوبت تغییر کرده است. صفحه را تازه‌سازی کنید.'], 409);
        }
        $deletedAppointment = clone $appointment;
        DB::transaction(function () use ($appointment, $request) {
            $this->syncAppointmentInventoryStock([], collect([$appointment]));
            ResourceEarningLine::query()->where('appointment_id', $appointment->id)->delete();
            $this->reverseAppointmentWalletSourceUnless($request, "appointment-referral|{$appointment->id}", false);
            $this->reverseAppointmentWalletSourceUnless($request, "appointment-wallet|{$appointment->id}", false);
            $appointment->delete();
            $this->syncMonthlySalesBonusLines($appointment->month);
        });
        $this->broadcastAppointmentChange($deletedAppointment, 'deleted');
        return response()->noContent();
    }

    /** Realtime delivery must never turn an already committed database write into an API failure. */
    private function broadcastAppointmentChange(Appointment $appointment, string $action = 'updated'): void
    {
        try {
            broadcast(AppointmentChanged::fromAppointment($appointment, $action))->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * ثبت یک نوبت از بخش‌هایی مانند پیگیری، بدون بازنویسی کل ماه.
     */
    public function storeSingle(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'string', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/'],
            'day_num' => ['required', 'integer', 'between:1,31'],
            'lastname' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:20'],
            'time' => ['required', 'date_format:H:i'],
            'doctor' => ['nullable', 'string', 'max:255'],
            'consultant' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());

        $sortOrder = ((int) Appointment::query()
            ->where('month', $data['month'])
            ->where('day_num', $data['day_num'])
            ->max('sort_order')) + 1;

        $appointment = Appointment::create([
            ...$data,
            'sort_order' => $sortOrder,
            'status' => 'وقت داده شد',
            'services' => [],
            'service_types' => [],
            'amount' => 0,
            'original_amount' => 0,
            'discount' => 0,
            'debt' => 0,
            'new_customer' => false,
        ]);

        return response()->json(['appointment' => $appointment], 201);
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

    public function payPatientDebt(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_account' => ['nullable', 'string', 'max:100'],
        ]);

        $result = DB::transaction(function () use ($request, $patient, $data) {
            $remaining = (int) $data['amount'];
            $appointments = Appointment::query()
                ->where(function ($query) use ($patient) {
                    if ($patient->file_number) $query->where('file_number', $patient->file_number);
                    if ($patient->phone) $query->{$patient->file_number ? 'orWhere' : 'where'}('phone', $patient->phone);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (Appointment $appointment) => $this->signedMoneyToInteger($appointment->debt) > 0);

            $outstanding = $appointments->sum(fn (Appointment $appointment) => $this->signedMoneyToInteger($appointment->debt));
            if ($remaining > $outstanding) {
                abort(422, 'مبلغ پرداختی نباید از مانده بدهی بیشتر باشد.');
            }

            $updated = [];
            foreach ($appointments as $appointment) {
                if ($remaining <= 0) break;
                $before = clone $appointment;
                $debt = $this->signedMoneyToInteger($appointment->debt);
                $paid = min($debt, $remaining);
                $details = is_array($appointment->payment_details) ? $appointment->payment_details : [];
                $details['debt_payments'] = array_merge($details['debt_payments'] ?? [], [[
                    'amount' => $paid,
                    'method' => $data['payment_method'] ?? null,
                    'account' => $data['payment_account'] ?? null,
                    'paid_at' => now()->toDateTimeString(),
                ]]);
                $appointment->update([
                    'debt' => $debt - $paid,
                    'payment_method' => $data['payment_method'] ?? $appointment->payment_method,
                    'payment_account' => $data['payment_account'] ?? $appointment->payment_account,
                    'payment_details' => $details,
                ]);
                $this->recordBalanceAudit($request, $appointment, $before);
                $updated[] = [
                    'id' => $appointment->id,
                    'debt' => $debt - $paid,
                    'payment_details' => $details,
                ];
                $remaining -= $paid;
            }

            return $updated;
        });

        return response()->json([
            'message' => 'پرداخت بدهی با موفقیت ثبت شد.',
            'appointments' => $result,
            'outstanding_debt' => $patient->fresh()->outstanding_debt,
        ]);
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

        $inventoryIds = collect($services)->flatMap(fn ($service) => collect($service['addons'] ?? [])
            ->pluck('inventory_id'))->filter()->unique()->values();
        $addonDefinitionIds = collect($services)->flatMap(fn ($service) => collect($service['addons'] ?? [])
            ->pluck('addon_definition_id'))->filter()->unique()->values();
        $inventory = Inventory::query()
            ->where(function ($query) use ($names, $inventoryIds) {
                $query->whereIn('name', $names);
                if ($inventoryIds->isNotEmpty()) $query->orWhereIn('id', $inventoryIds);
            })->get();
        $prices = $inventory->pluck('amount', 'name');
        $inventoryById = $inventory->keyBy('id');
        $addonDefinitionsById = InventoryAddonDefinition::query()
            ->whereIn('id', $addonDefinitionIds)
            ->where('active', true)
            ->get()
            ->keyBy('id');

        $originalAmount = 0;
        $totalDiscount = 0;
        $totalSurcharge = 0;
        $normalizeLine = function (array $line) use ($prices, $inventoryById, $addonDefinitionsById, &$originalAmount, &$totalDiscount, &$totalSurcharge): array {
            $quantity = max(0, (float) ($line['cc'] ?? 0));
            $inventoryItem = ! empty($line['addon_definition_id'])
                ? $addonDefinitionsById->get((int) $line['addon_definition_id'])
                : (! empty($line['inventory_id']) ? $inventoryById->get((int) $line['inventory_id']) : null);
            $lineAmount = (int) round((float) ($inventoryItem?->amount ?? $prices[$line['name'] ?? ''] ?? 0) * $quantity);
            $adjustment = max(0, $this->signedMoneyToInteger($line['discount'] ?? 0));
            $line['adjustment_mode'] = ($line['adjustment_mode'] ?? '') === 'surcharge' ? 'surcharge' : 'discount';
            $line['surcharge_for_doctor_commission'] = $line['adjustment_mode'] === 'surcharge'
                && filter_var($line['surcharge_for_doctor_commission'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $discount = $line['adjustment_mode'] === 'discount' ? min($adjustment, $lineAmount) : 0;
            $surcharge = $line['adjustment_mode'] === 'surcharge' ? $adjustment : 0;
            $line['discount'] = $line['adjustment_mode'] === 'surcharge' ? $surcharge : $discount;
            $originalAmount += $lineAmount;
            $totalDiscount += $discount;
            $totalSurcharge += $surcharge;

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
            'calculated' => $prices->isNotEmpty() || $addonDefinitionsById->isNotEmpty(),
            'original_amount' => $originalAmount,
            'discount' => $totalDiscount,
            'surcharge' => $totalSurcharge,
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
            // انتخاب خدمت در «وقت داده شد» رزرو موجودی نیست. مصرف و کنترل
            // موجودی فقط زمانی انجام می‌شود که کار واقعاً انجام شده باشد.
            $previous = $this->inventoryUsageForAppointment($pair['previous']);
            $current = $this->inventoryUsageForAppointment($pair['current']);
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
            foreach ($this->inventoryUsageForAppointment($appointment) as $name => $quantity) {
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
            // آیتم‌های صفرمبلغ در این پروژه برای دسته/تگ خدمات هم استفاده
            // می‌شوند و نباید با موجودی صفر مانع ثبت نوبت شوند.
            if ((float) $inventory->amount <= 0) continue;
            if ((float) $inventory->stock + $quantity < -0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inventory' => ["موجودی «{$name}» برای ثبت این خدمت کافی نیست."],
                ]);
            }
        }

        foreach ($totals as $name => $quantity) {
            $inventory = $inventories->get($name);
            if (! $inventory) continue;
            if ((float) $inventory->amount <= 0) continue;
            $inventory->update(['stock' => max(0, (float) $inventory->stock + $quantity)]);
        }

        foreach ($movements as $movement) {
            $inventory = $inventories->get($movement['name']);
            if (! $inventory) continue;
            if ((float) $inventory->amount <= 0) continue;
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

    private function syncServiceFollowups(Appointment $appointment): void
    {
        if (trim((string) $appointment->done) !== 'انجام شد') return;
        $services = collect($appointment->services ?? [])->pluck('name')->filter()->unique()->values();
        if ($services->isEmpty()) return;
        $completedAt = $appointment->completed_at ? now()->parse($appointment->completed_at) : now();
        Inventory::query()->whereIn('name', $services)->where('followup_days', '>', 0)->get()->each(function (Inventory $item) use ($appointment, $completedAt) {
            $sourceKey = sha1(implode('|', [$appointment->phone, $appointment->month, $appointment->day_num, $item->id]));
            ServiceFollowup::updateOrCreate(['source_key' => $sourceKey], [
                'appointment_id' => $appointment->id,
                'inventory_id' => $item->id,
                'service_name' => $item->name,
                'patient_name' => $appointment->lastname,
                'patient_phone' => $appointment->phone,
                'completed_at' => $completedAt,
                'due_date' => $completedAt->copy()->addDays((int) $item->followup_days)->toDateString(),
                'followup_days' => (int) $item->followup_days,
                'status' => 'pending',
            ]);
        });
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

    private function inventoryUsageForAppointment(?Appointment $appointment): array
    {
        if (! $appointment || trim((string) $appointment->done) !== 'انجام شد') {
            return [];
        }

        return $this->inventoryUsageForServices($appointment->services ?? []);
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
            // جانبی‌های مستقل فقط در مبلغ نوبت اثر دارند و پورسانت ندارند.
            if (! empty($line['addon_definition_id'])) {
                continue;
            }
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

                $this->createPreferredCommissionLine($appointment, $line, $inventory, $target['type'], $resource);
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
            'addon_definition_id' => ! empty($line['addon_definition_id']) ? (int) $line['addon_definition_id'] : null,
            'quantity' => max(1, (float) ($line['cc'] ?? 1)),
            'discount' => max(0, (float) ($line['discount'] ?? 0)),
            'surcharge' => ($line['adjustment_mode'] ?? '') === 'surcharge' ? max(0, (float) ($line['discount'] ?? 0)) : 0,
            'surcharge_for_doctor_commission' => (bool) ($line['surcharge_for_doctor_commission'] ?? false),
            'doctor' => trim((string) ($line['doctor'] ?? $parent['doctor'] ?? '')),
            'consultant' => trim((string) ($line['consultant'] ?? $parent['consultant'] ?? '')),
            'index' => $index,
            'is_addon' => $isAddon,
        ];
    }

    private function createBaseCommissionLine(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource): void
    {
        $amounts = $this->earningAmounts($line, $inventory, (bool) $resource->commission_after_materials, $type !== 'doctor' || $line['surcharge_for_doctor_commission']);
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

    /** اولویت قطعی: قانون اختصاصی انبار، سپس قانون پیش فرض انبار، سپس منابع. */
    private function createPreferredCommissionLine(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource): void
    {
        $specific = $inventory->commissions
            ->where('recipient_type', $type)
            ->filter(fn (InventoryCommission $commission) => $this->commissionMatchesResource($commission, $resource));
        if ($specific->isNotEmpty()) {
            $this->createInventoryCommissionLines($appointment, $line, $inventory, $type, $resource);
            return;
        }

        if ((float) $inventory->default_commission_value > 0) {
            $amounts = $this->earningAmounts($line, $inventory, false, $type !== 'doctor' || $line['surcharge_for_doctor_commission']);
            $commissionType = $inventory->default_commission_type === 'fixed' ? 'fixed' : 'percent';
            $value = (float) $inventory->default_commission_value;
            $amount = $this->calculateCommissionAmount($commissionType, $value, $amounts['net'], $line['quantity']);
            if ($amount > 0) ResourceEarningLine::create($this->earningPayload($appointment, $line, $inventory, $type, $resource, 'inventory_default_commission', $commissionType, $value, $amount, [...$amounts, 'base' => $amounts['net']], "پورسانت پیش‌فرض انبار {$value}".($commissionType === 'percent' ? '%' : ' تومان')." برای {$line['name']}"));
            return;
        }

        $this->createBaseCommissionLine($appointment, $line, $inventory, $type, $resource);
    }

    private function createInventoryCommissionLines(Appointment $appointment, array $line, Inventory $inventory, string $type, Doctor|Staff $resource): void
    {
        $commissions = $inventory->commissions
            ->where('recipient_type', $type)
            ->filter(fn (InventoryCommission $commission) => $this->commissionMatchesResource($commission, $resource));

        foreach ($commissions as $commission) {
            $amounts = $this->earningAmounts($line, $inventory, false, $type !== 'doctor' || $line['surcharge_for_doctor_commission']);
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
                'calculation_source' => str_starts_with($earningType, 'inventory') ? 'inventory' : 'resource',
                'calculation_source_label' => str_starts_with($earningType, 'inventory') ? 'تنظیمات انبار' : 'تنظیمات کلی منبع',
                'resource_bonus' => (float) ($resource->bonus ?? 0),
                'resource_sales_bonus_enabled' => (bool) ($resource->sales_bonus_enabled ?? false),
                'resource_sales_bonus_tiers' => $resource->sales_bonus_tiers ?? [],
                'inventory_amount' => (float) ($inventory->amount ?? 0),
                'inventory_material_price' => (float) ($inventory->price ?? 0),
                'inventory_default_commission_type' => $inventory->default_commission_type,
                'inventory_default_commission_value' => (float) $inventory->default_commission_value,
            ],
            'audit_events' => [[
                'event' => 'created_from_appointment',
                'at' => now()->toIso8601String(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name,
                'appointment_id' => $appointment->id,
                'source' => str_starts_with($earningType, 'inventory') ? 'inventory' : 'resource',
            ]],
            'description' => $description,
        ];
    }

    private function earningAmounts(array $line, Inventory $inventory, bool $afterMaterials, bool $includeSurcharge = true): array
    {
        $gross = (float) ($inventory->amount ?? 0) * $line['quantity'];
        $discount = min($line['discount'], $gross);
        $net = max(0, $gross + ($includeSurcharge ? $line['surcharge'] : 0) - $discount);
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
