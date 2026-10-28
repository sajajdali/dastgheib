<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Services\CustomerLevelService;
use App\Support\VoipIntegration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only patient card for the call-center panel (voip.s8n.ir).
 * Same numbers as the patient profile page (parvande.vue), looked up by phone.
 * Auth: "Authorization: Bearer <token>" from Settings → «ارتباط با VoIP»; the token names the tenant. No session, no writes.
 */
class VoipPatientController extends Controller
{
    private const DIGITS = [
        '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
        '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9',
    ];

    private const LEVELS = [
        'problematic' => 'مشتری دردسرساز', 'blue' => 'مشتری آبی', 'silver' => 'مشتری نقره‌ای', 'gold' => 'مشتری طلایی (CIP)',
    ];

    private const EXTRA_FIELDS = [
        'gender' => 'جنسیت', 'birth_date' => 'تاریخ تولد', 'area' => 'محدوده', 'financial_status' => 'وضعیت مالی',
        'national_id' => 'کد ملی', 'foreign_national_code' => 'کد اتباع', 'father_name' => 'نام پدر', 'marital_status' => 'وضعیت تأهل',
        'marriage_date' => 'تاریخ ازدواج', 'education' => 'تحصیلات', 'second_phone' => 'شماره تماس دوم', 'address' => 'آدرس محل سکونت',
        'patient_history' => 'تیپ شخصیتی', 'medical_history' => 'سوابق پزشکی',
    ];

    public function show(Request $request, string $phone, CustomerLevelService $levels, AppointmentController $appointments, SatisfactionController $satisfaction): JsonResponse
    {
        if (! VoipIntegration::accepts((string) $request->bearerToken())) {
            \Log::warning('voip patient api: rejected token');
            $on = VoipIntegration::settings()['enabled'];
            abort($on ? 401 : 403, $on ? 'توکن نامعتبر است' : 'ارتباط با VoIP در تنظیمات غیرفعال است');
        }

        $phone = preg_replace('/\D/', '', strtr($phone, self::DIGITS));
        if (str_starts_with($phone, '98')) $phone = '0'.substr($phone, 2);
        if (str_starts_with($phone, '9')) $phone = '0'.$phone;
        abort_unless(preg_match('/^0\d{9,10}$/', $phone), 422, 'شماره نامعتبر است');

        $patient = Patient::whereRaw($this->digitColumn('phone').' = ?', [$phone])->orderBy('id')->first();
        if (! $patient) {
            return response()->json(['found' => false, 'phone' => $phone]);
        }
        $levels->decorate(collect([$patient]));

        // the same history and satisfaction answers the profile page loads
        $historyRequest = Request::create('/', 'GET', array_filter(['file_number' => $patient->file_number, 'phone' => $patient->phone]));
        $rows = collect($appointments->patientHistory($historyRequest)->getData(true));
        $responses = collect($satisfaction->patientResponses($patient)->getData(true));

        return response()->json([
            'found' => true,
            'patient' => $this->profile($patient, $rows),
            'details' => $this->details($patient),
            'stats' => $this->stats($rows, (int) $patient->outstanding_debt),
            'services' => $rows->map(fn ($row) => $this->serviceRow($row))->values(),
            'satisfaction' => $this->satisfaction($responses),
        ]);
    }

    private function profile(Patient $patient, $rows): array
    {
        return [
            'id' => $patient->id,
            'name' => trim(($patient->first_name ?? '').' '.($patient->last_name ?? '')),
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'phone' => strtr((string) $patient->phone, self::DIGITS),
            'file_number' => $patient->file_number,
            'customer_level' => $patient->customer_level,
            'customer_level_label' => self::LEVELS[$patient->customer_level] ?? '',
            'problematic' => $patient->customer_level === 'problematic',
            'age' => $this->age($patient->birth_date),
            'city' => $patient->city ?: $patient->area,
            'visits' => $rows->count(),
            'services_total' => $rows->sum(fn ($row) => $this->num($row['amount'] ?? 0)),
            'wallet_balance' => (int) $patient->wallet_balance,
            'outstanding_debt' => (int) $patient->outstanding_debt,
            // a 256px square from the full photo (the stored thumbnail is only 50px)
            'avatar' => $this->avatar($patient),
            // fallback for the panel when no photo could be made here
            'avatar_path' => $patient->avatar_url ? parse_url($patient->avatar_url, PHP_URL_PATH) : null,
        ];
    }

    /** «اطلاعات تکمیلی پرونده»: fields switched on in the profile settings, or filled in. */
    private function details(Patient $patient): array
    {
        $enabled = AppSetting::getByKey('profile_fields');
        for ($i = 0; $i < 2 && is_string($enabled); $i++) $enabled = json_decode($enabled, true);
        $enabled = is_array($enabled) ? $enabled : [];

        $out = [];
        foreach (self::EXTRA_FIELDS as $key => $label) {
            $value = trim((string) ($patient->{$key} ?? ''));
            if ($value === '' && empty($enabled[$key])) continue;
            if ($key === 'national_id' || $key === 'foreign_national_code') $value = $value === '' ? '' : '•••••'.substr($value, -4);
            if ($key === 'birth_date' || $key === 'marriage_date') $value = str_replace('-', '/', $value);
            $out[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }
        return $out;
    }

    /** «گزارش کلی مراجعه‌کننده»: the formulas of profileStats / profileStatCards. */
    private function stats($rows, int $debt): array
    {
        $total = $rows->count();
        $paid = $rows->sum(fn ($r) => $this->num($r['amount'] ?? 0));
        $referrals = $rows->filter(fn ($r) => trim((string) ($r['referrer_phone'] ?? '')) !== '' || $this->num($r['referral_score'] ?? 0) > 0)->count();
        $commission = $rows->sum(fn ($r) => $this->num($r['referral_score'] ?? 0));
        $canceled = $rows->filter(fn ($r) => preg_match('/کنسل|لغو|cancel/u', (string) ($r['status'] ?? '')))->count();
        $discount = $rows->sum(fn ($r) => $this->num($r['discount'] ?? 0));
        $original = $rows->sum(fn ($r) => $this->num($r['original_amount'] ?? 0) ?: $this->num($r['amount'] ?? 0) + $this->num($r['discount'] ?? 0));
        $lead = $rows->map(fn ($r) => $this->leadHours($r))->filter(fn ($h) => $h !== null && $h >= 0);
        $debtRows = $rows->sum(fn ($r) => $this->num($r['debt'] ?? 0));

        return [
            'visits' => $total,
            'paid' => $paid,
            'paid_ratio' => $this->ratio($paid, max($paid + $debtRows, 1)),
            'referrals' => $referrals,
            'commission' => $commission,
            'canceled' => $canceled,
            'cancel_rate' => $total ? round($canceled / $total * 100, 1) : 0,
            'lead_hours' => $lead->isNotEmpty() ? round($lead->avg(), 1) : null,
            'avg_discount' => $original > 0 ? round($discount / $original * 100, 1) : 0,
            'debt' => $debt,
        ];
    }

    /** One «سوابق خدمات» row, already in display form. */
    private function serviceRow(array $row): array
    {
        $services = $this->list($row['services'] ?? []);
        $paths = collect($row['history_section_paths'] ?? []);
        $details = is_array($row['payment_details'] ?? null) ? $row['payment_details'] : [];
        $payment = array_values(array_filter([
            $row['payment_method'] ?? '', $row['payment_account'] ?? '',
            $this->num($details['cash'] ?? 0) > 0 ? 'نقدی '.number_format($this->num($details['cash'])).' تومان' : '',
            $this->num($details['card'] ?? 0) > 0 ? 'کارت '.number_format($this->num($details['card'])).' تومان' : '',
        ]));
        $check = is_array($details['check'] ?? null) ? $details['check'] : [];

        return [
            'id' => $row['id'] ?? null,
            'date' => trim(str_replace('-', '/', (string) ($row['month'] ?? '')).(isset($row['day_num']) ? '/'.str_pad((string) $row['day_num'], 2, '0', STR_PAD_LEFT) : '')),
            'time' => $row['time'] ?? '',
            'service' => collect($services)->filter(fn ($s) => is_array($s) && ! empty($s['name']))
                ->map(fn ($s) => $s['name'].(! empty($s['cc']) ? ' - '.$s['cc'].'cc' : ''))->implode('، '),
            'sections' => $paths->pluck('section')->filter()->unique()->values(),
            'subsections' => $paths->pluck('subsection')->filter()->unique()->values(),
            'doctor' => collect($services)->pluck('doctor')->filter()->unique()->implode('، '),
            'consultant' => collect($services)->pluck('consultant')->filter()->unique()->implode('، '),
            'amount' => $this->num($row['amount'] ?? 0),
            'payment' => implode('، ', $payment),
            'check_due' => (string) ($check['dueDate'] ?? $check['due_date'] ?? ''),
            'status' => $row['status'] ?? '',
            'arrived_at' => $this->clock($row['arrived_at'] ?? null),
            'done' => collect($this->list($row['done'] ?? []))->filter()->implode('، '),
            'completed_at' => $this->clock($row['completed_at'] ?? null),
            'debt' => $this->num($row['debt'] ?? 0),
            'registered_by' => $row['registered_by'] ?? '',
        ];
    }

    /** «میزان رضایت‌مندی»: average of every rated answer and the count per score. */
    private function satisfaction($responses): array
    {
        $answers = $responses->flatMap(fn ($r) => $r['answers'] ?? []);
        $scores = $answers->pluck('score')->map(fn ($s) => (int) $s)->filter(fn ($s) => $s > 0);
        $labels = [5 => 'عالی', 4 => 'خوب', 3 => 'متوسط', 2 => 'بد', 1 => 'ضعیف'];
        foreach ($answers as $answer) {
            foreach ($answer['options'] ?? [] as $option) {
                $score = (int) ($option['score'] ?? 0);
                if (isset($labels[$score]) && ! empty($option['label']) && ! isset($seen[$score])) { $labels[$score] = $option['label']; $seen[$score] = true; }
            }
        }
        return [
            'average' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
            'responses' => $responses->count(),
            'levels' => collect([5, 4, 3, 2, 1])->map(fn ($s) => ['score' => $s, 'label' => $labels[$s], 'count' => $scores->filter(fn ($x) => $x === $s)->count()])->values(),
        ];
    }

    /** Center-cropped 256px WebP of the profile photo as a data URL, or null. */
    private function avatar(Patient $patient): ?string
    {
        $path = $patient->profile_photo_path;
        if (! $path || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) return null;
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) app(\App\Services\TenantStorageService::class)->importLegacyCurrentTenant();
            if (! $disk->exists($path) || $disk->size($path) > 8 * 1024 * 1024) return null;
            $src = @imagecreatefromstring($disk->get($path));
            if (! $src) return null;
            // phone photos keep their rotation in EXIF
            if (function_exists('exif_read_data') && preg_match('/\.jpe?g$/i', $path)) {
                $exif = @exif_read_data($disk->path($path));
                $turn = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
                if ($turn && ($rotated = imagerotate($src, $turn, 0))) { imagedestroy($src); $src = $rotated; }
            }
            $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
            $size = min(256, $side);
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
            ob_start(); imagewebp($dst, null, 82); $data = ob_get_clean();
            imagedestroy($src); imagedestroy($dst);
            return $data ? 'data:image/webp;base64,'.base64_encode($data) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function leadHours(array $row): ?float
    {
        if (empty($row['created_at']) || empty($row['month']) || empty($row['day_num'])) return null;
        [$jy, $jm] = array_map('intval', array_pad(explode('/', str_replace('-', '/', (string) $row['month'])), 2, 0));
        $jd = (int) $row['day_num'];
        if (! $jy || ! $jm || $jm > 12 || ! $jd || $jd > 31) return null;
        [$gy, $gm, $gd] = $this->jalaliToGregorian($jy, $jm, $jd);
        [$h, $m] = array_map('intval', array_pad(explode(':', (string) ($row['time'] ?: '00:00')), 2, 0));
        try {
            $at = Carbon::create($gy, $gm, $gd, $h, $m, 0, config('app.timezone'));
            return $at->diffInMinutes(Carbon::parse($row['created_at']), false) / -60;
        } catch (\Throwable) {
            return null;
        }
    }

    private function age(?string $birth): string
    {
        $value = str_replace('-', '/', strtr(trim((string) $birth), self::DIGITS));
        if (! preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $p)) return '';
        [$y, $m, $d] = [(int) $p[1], (int) $p[2], (int) $p[3]];
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31) return '';
        if ($y < 1700) [$y, $m, $d] = $this->jalaliToGregorian($y, $m, $d);
        try {
            $years = Carbon::create($y, $m, $d)->diffInYears(now(), false);
        } catch (\Throwable) {
            return '';
        }
        if ($years < 0) return '';
        return $years < 1 ? 'کمتر از یک سال' : ((int) $years).' سال';
    }

    private function clock(mixed $value): string
    {
        if (! $value) return '';
        try { return Carbon::parse($value)->format('H:i'); } catch (\Throwable) { return substr((string) $value, 11, 5); }
    }

    private function list(mixed $value): array
    {
        if (is_array($value)) return $value;
        $text = trim((string) $value);
        if ($text === '') return [];
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : array_map('trim', preg_split('/[,،|]+/u', $text));
    }

    private function num(mixed $value): int
    {
        return (int) round((float) preg_replace('/[^\d.-]/', '', strtr(str_replace(',', '', (string) $value), self::DIGITS)));
    }

    private function ratio(float $value, float $max): float
    {
        return $max > 0 ? round(max(0, min(100, $value / $max * 100)), 1) : 0;
    }

    private function digitColumn(string $column): string
    {
        foreach (self::DIGITS as $from => $to) $column = "REPLACE({$column}, '{$from}', '{$to}')";
        return $column;
    }

    /** @return array{int, int, int} */
    private function jalaliToGregorian(int $year, int $month, int $day): array
    {
        $jy = $year + 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4)
            + $day + ($month < 7 ? (($month - 1) * 31) : ((($month - 7) * 30) + 186));
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $monthDays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        for ($gm = 1; $gm <= 12; $gm++) {
            $dim = $monthDays[$gm] + ($gm === 2 && $leap ? 1 : 0);
            if ($gd <= $dim) return [$gy, $gm, $gd];
            $gd -= $dim;
        }
        return [$gy, 12, 31];
    }
}
