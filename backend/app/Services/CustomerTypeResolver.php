<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentFinancialTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * مشتری قدیم: حداقل یک نوبت قبلی دارد که هم خدمت (خدمت) دارد و هم پرداخت ثبت‌شده.
 * مشتری جدید: حتی با نوبت قبلی، اگر آن شرط برقرار نباشد همچنان جدید است.
 */
class CustomerTypeResolver
{
    /** @var array<int, bool> */
    private array $paidServiceCache = [];

    public function isNewCustomer(
        ?string $fileNumber = null,
        ?string $phone = null,
        ?int $excludeAppointmentId = null,
        ?string $beforeMonth = null,
        ?int $beforeDayNum = null,
    ): bool {
        return ! $this->hasPriorPaidServiceAppointment(
            $fileNumber,
            $phone,
            $excludeAppointmentId,
            $beforeMonth,
            $beforeDayNum,
        );
    }

    public function isNewForAppointment(Appointment $appointment): bool
    {
        return $this->isNewCustomer(
            $appointment->file_number,
            $appointment->phone,
            $appointment->id ? (int) $appointment->id : null,
            $appointment->month,
            $appointment->day_num !== null ? (int) $appointment->day_num : null,
        );
    }

    public function hasPriorPaidServiceAppointment(
        ?string $fileNumber = null,
        ?string $phone = null,
        ?int $excludeAppointmentId = null,
        ?string $beforeMonth = null,
        ?int $beforeDayNum = null,
    ): bool {
        $fileNumber = trim((string) $fileNumber);
        $phone = $this->normalizePhoneDigits($phone);
        if ($fileNumber === '' && $phone === '') {
            return false;
        }

        $query = Appointment::query()->where(function ($match) use ($fileNumber, $phone) {
            if ($fileNumber !== '') {
                $match->where('file_number', $fileNumber);
            }
            if ($phone !== '') {
                $method = $fileNumber !== '' ? 'orWhere' : 'where';
                $match->{$method}('phone', $phone);
            }
        });

        if ($excludeAppointmentId) {
            $query->where('id', '<>', $excludeAppointmentId);
        }

        if ($beforeMonth !== null && $beforeMonth !== '') {
            $day = max(1, (int) ($beforeDayNum ?: 1));
            $query->where(function ($prior) use ($beforeMonth, $day, $excludeAppointmentId) {
                $prior->where('month', '<', $beforeMonth)
                    ->orWhere(function ($sameMonth) use ($beforeMonth, $day) {
                        $sameMonth->where('month', $beforeMonth)->where('day_num', '<', $day);
                    });
                if ($excludeAppointmentId) {
                    // همان روز، نوبت‌های با شناسهٔ کوچک‌تر قبلی محسوب می‌شوند.
                    $prior->orWhere(function ($sameDay) use ($beforeMonth, $day, $excludeAppointmentId) {
                        $sameDay->where('month', $beforeMonth)
                            ->where('day_num', $day)
                            ->where('id', '<', $excludeAppointmentId);
                    });
                }
            });
        }

        $candidates = $query
            ->orderBy('month')
            ->orderBy('day_num')
            ->orderBy('id')
            ->get(['id', 'services', 'payment_details', 'wallet_applied', 'amount']);

        if ($candidates->isEmpty()) {
            return false;
        }

        $paidIds = $this->appointmentsWithRegisteredPayment($candidates->pluck('id'));

        foreach ($candidates as $candidate) {
            if (! $this->appointmentHasNamedService($candidate)) {
                continue;
            }
            if ($paidIds->has((int) $candidate->id) || $this->legacyPaymentAmount($candidate) > 0) {
                return true;
            }
        }

        return false;
    }

    public function appointmentHasPaidService(Appointment $appointment): bool
    {
        if (! $this->appointmentHasNamedService($appointment)) {
            return false;
        }

        return $this->appointmentHasRegisteredPayment($appointment);
    }

    /**
     * آیا برای این نوبت حداقل یک پرداخت/تسویهٔ بدهی یا مبلغ legacy ثبت شده است؟
     * مبنای نمایش و پرداخت پورسانت پزشک/پرسنل تا قبل از این false می‌ماند.
     */
    public function appointmentHasRegisteredPayment(Appointment|array $appointment): bool
    {
        $id = (int) (is_array($appointment) ? ($appointment['id'] ?? 0) : ($appointment->id ?? 0));
        if ($id > 0 && $this->appointmentsWithRegisteredPayment(collect([$id]))->has($id)) {
            return true;
        }

        return $this->legacyPaymentAmount(
            is_array($appointment) ? (object) $appointment : $appointment
        ) > 0;
    }

    public function appointmentHasNamedService(Appointment|array|null $appointment): bool
    {
        $services = is_array($appointment)
            ? ($appointment['services'] ?? [])
            : ($appointment?->services ?? []);

        foreach ($services ?: [] as $service) {
            if (! is_array($service)) {
                continue;
            }
            if (trim((string) ($service['name'] ?? '')) !== '') {
                return true;
            }
            foreach ($service['addons'] ?? [] as $addon) {
                if (is_array($addon) && trim((string) ($addon['name'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * شناسهٔ نوبت‌هایی که حداقل یک پرداخت یا تسویهٔ بدهی ثبت‌شده (غیر باطل) دارند،
     * یا کیف پول روی خود نوبت برای خدمت اعمال شده است.
     *
     * @param  iterable<int|string>  $appointmentIds
     * @return Collection<int, true>
     */
    public function appointmentsWithRegisteredPayment(iterable $appointmentIds): Collection
    {
        $ids = collect($appointmentIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $uncached = $ids->reject(fn (int $id) => array_key_exists($id, $this->paidServiceCache));
        if ($uncached->isNotEmpty() && Schema::hasTable('appointment_financial_transactions')) {
            $ledgerPaid = AppointmentFinancialTransaction::query()
                ->whereIn('appointment_id', $uncached)
                ->whereIn('type', ['payment', 'debt_settlement'])
                ->whereNull('voided_at')
                ->where('amount', '>', 0)
                ->distinct()
                ->pluck('appointment_id')
                ->map(fn ($id) => (int) $id);

            foreach ($uncached as $id) {
                $this->paidServiceCache[$id] = false;
            }
            foreach ($ledgerPaid as $id) {
                $this->paidServiceCache[$id] = true;
            }
        } elseif ($uncached->isNotEmpty()) {
            foreach ($uncached as $id) {
                $this->paidServiceCache[$id] = false;
            }
        }

        return $ids->filter(fn (int $id) => $this->paidServiceCache[$id] ?? false)
            ->mapWithKeys(fn (int $id) => [$id => true]);
    }

    public function legacyPaymentAmount(object $appointment): float
    {
        $wallet = max(0, $this->number($appointment->wallet_applied ?? 0));
        $details = is_array($appointment->payment_details ?? null) ? $appointment->payment_details : [];
        if (array_key_exists('ledger_total', $details)) {
            return max(0, $this->number($details['ledger_total'])) + $wallet;
        }

        $breakdown = max(0, $this->number($details['cash'] ?? 0))
            + max(0, $this->number($details['card'] ?? 0))
            + max(0, $this->number(data_get($details, 'check.amount', 0)));
        $debtPayments = collect($details['debt_payments'] ?? [])
            ->sum(fn ($payment) => is_array($payment) ? $this->number($payment['amount'] ?? 0) : 0);

        return $breakdown + max(0, (float) $debtPayments) + $wallet;
    }

    /**
     * پرچم new_customer همهٔ نوبت‌های بیمار را با قاعدهٔ فعلی هم‌تراز می‌کند.
     *
     * @return Collection<int, Appointment> نوبت‌هایی که پرچم‌شان عوض شد
     */
    public function reclassifyPatientAppointments(?string $fileNumber, ?string $phone): Collection
    {
        $fileNumber = trim((string) $fileNumber);
        $phone = $this->normalizePhoneDigits($phone);
        if ($fileNumber === '' && $phone === '') {
            return collect();
        }

        $appointments = Appointment::query()
            ->where(function ($match) use ($fileNumber, $phone) {
                if ($fileNumber !== '') {
                    $match->where('file_number', $fileNumber);
                }
                if ($phone !== '') {
                    $method = $fileNumber !== '' ? 'orWhere' : 'where';
                    $match->{$method}('phone', $phone);
                }
            })
            ->orderBy('month')
            ->orderBy('day_num')
            ->orderBy('id')
            ->get();

        $changed = collect();
        foreach ($appointments as $appointment) {
            $isNew = $this->isNewForAppointment($appointment);
            if ((bool) $appointment->new_customer === $isNew) {
                continue;
            }
            $appointment->new_customer = $isNew;
            $appointment->save();
            $changed->push($appointment);
        }

        return $changed;
    }

    public function normalizePhoneDigits(mixed $value): string
    {
        $digits = strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\D+/', '', $digits) ?? '';
    }

    private function number(mixed $value): float
    {
        return (float) str_replace([',', '٬', ' '], '', (string) $value);
    }
}
