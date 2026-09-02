<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\ShsmsService;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendLeadAlertSms
{
    use Queueable;

    public function __construct(public string $report)
    {
    }

    public function handle(ShsmsService $sms): void
    {
        $raw = AppSetting::getByKey('sms_lead_alerts', '{}');
        $settings = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($settings) || ! ($settings['enabled'] ?? false)) return;
        $recipients = collect($settings['recipients'] ?? [])->filter()->unique();
        if ($recipients->isEmpty()) return;

        $messages = $this->report === 'night'
            ? $this->nightMessages($settings)
            : $this->morningMessages($settings);

        foreach ($messages as $message) {
            $template = trim((string) ($settings[($message['key'] ?? '').'_template'] ?? ''));
            if ($template === '') continue;
            foreach ($recipients as $recipient) {
                try {
                    $sms->sendTemplate($recipient, $template, $message['params'] ?? []);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    private function morningMessages(array $settings): array
    {
        [$year, $month, $day] = $this->jalaliToday();
        $messages = [];

        if ($settings['daily_appointments'] ?? false) {
            $count = Appointment::query()->where('month', sprintf('%04d-%02d', $year, $month))->where('day_num', $day)->count();
            if ($count > 0) $messages[] = ['key' => 'daily_appointments', 'params' => [number_format($count), $this->jalaliDate($year, $month, $day), $this->clinicName()]];
        }

        if ($settings['inventory_empty'] ?? false) {
            Inventory::query()->where('active', true)->whereRaw('COALESCE(stock, 0) <= 0')->get(['name', 'stock'])
                ->each(fn ($item) => $messages[] = ['key' => 'inventory_empty', 'params' => [(string) $item->name, number_format((int) $item->stock), $this->clinicName()]]);
        }

        if (($settings['active_tickets'] ?? false) && ($count = Ticket::query()->where('status', 'active')->count()) > 0) {
            $messages[] = ['key' => 'active_tickets', 'params' => [number_format($count), $this->clinicName()]];
        }

        return $messages;
    }

    private function nightMessages(array $settings): array
    {
        if (! ($settings['daily_financial'] ?? false)) return [];
        [$year, $month, $day] = $this->jalaliToday();
        $appointments = Appointment::query()
            ->where('month', sprintf('%04d-%02d', $year, $month))
            ->where('day_num', $day)->get();

        $revenue = $appointments->sum(fn ($item) => $this->number($item->amount));
        if ($revenue <= 0) return [];

        $inventory = Inventory::query()->get()->keyBy('name');
        $doctors = Doctor::query()->pluck('bonus', 'name');
        $staff = Staff::query()->pluck('bonus', 'name');
        $cost = 0;
        $commissions = 0;

        foreach ($appointments as $appointment) {
            foreach ($appointment->services ?? [] as $service) {
                $item = $inventory->get($service['name'] ?? '');
                $quantity = (float) ($service['cc'] ?? 0);
                if (! $item || $quantity <= 0) continue;
                $lineRevenue = $this->number($item->amount) * $quantity;
                $cost += $this->number($item->price) * $quantity;
                $commissions += $lineRevenue * ((float) ($doctors[$service['doctor'] ?? ''] ?? 0) / 100);
                $commissions += $lineRevenue * ((float) ($staff[$service['consultant'] ?? ''] ?? 0) / 100);
            }
        }

        $profit = max(0, $revenue - $cost - $commissions);
        return [[
            'key' => 'daily_financial',
            'params' => [number_format($revenue), number_format($profit), $this->jalaliDate($year, $month, $day), $this->clinicName()],
        ]];
    }

    private function number(mixed $value): float
    {
        return (float) preg_replace('/[^\d.-]/', '', (string) $value);
    }

    private function jalaliToday(): array
    {
        $gy = (int) now('Asia/Tehran')->format('Y');
        $gm = (int) now('Asia/Tehran')->format('n');
        $gd = (int) now('Asia/Tehran')->format('j');
        $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $jm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);
        return [$jy, $jm, $jd];
    }

    private function jalaliDate(int $year, int $month, int $day): string
    {
        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    private function clinicName(): string
    {
        return (string) AppSetting::getByKey('clinic_name', '');
    }
}
