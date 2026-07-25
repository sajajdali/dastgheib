<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\AppointmentBalanceAudit;
use App\Models\AppointmentNoteMessage;
use App\Models\Patient;
use App\Models\Inventory;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Services\CustomerLevelService;

class AppointmentController extends Controller
{
    // دریافت نوبت‌ها (فقط کل نوبت‌ها را برمی‌گرداند)
    public function getAppointments(CustomerLevelService $levels)
    {
        $appointments = Appointment::query()
            ->orderBy('month')
            ->orderBy('day_num')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json($appointments);
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

        return response()->json($appointments);
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

        // 🟢 بخش حیاتی: جلوگیری از پاک شدن کل دیتابیس
        DB::transaction(function () use ($month, $appointments, $request) {
            $desiredWalletSources = [];
            
            if ($month) {
                // فقط و فقط نوبت‌های همین ماه پاک می‌شوند
                $existingAppointments = Appointment::query()
                    ->where('month', $month)
                    ->get();
                $existingById = $existingAppointments->keyBy('id');
                $existingByKey = $existingAppointments->keyBy(fn (Appointment $item) => $this->appointmentAuditKey($item->toArray()));

                Appointment::where('month', $month)->delete();
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
                    $this->recordBalanceAudit($request, $created, $previousAppointment);

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
            }

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
                'phone' => $audit->phone,
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

        return response()->json(
            $query
                ->orderByDesc('created_at')
                ->get([
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
                ])
        );
    }
}
