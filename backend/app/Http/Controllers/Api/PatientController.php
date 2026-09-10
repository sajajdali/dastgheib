<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Services\CustomerLevelService;
use App\Models\AppSetting;
use App\Support\PatientPhoneVisibility;
use Carbon\Carbon;

class PatientController extends Controller
{
    public function nextFileNumber()
    {
        return response()->json([
            'file_number' => $this->nextFileNumberValue()
        ]);
    }

    public function checkDuplicate(Request $request)
    {
        $fileNumber = $this->normalizeDigits($request->query('file_number'));
        $phone = $this->normalizeDigits($request->query('phone'));

        return response()->json([
            'file_number_exists' => $fileNumber
                ? Patient::whereRaw($this->normalizedDigitColumn('file_number').' = ?', [$fileNumber])->exists()
                : false,

            'phone_exists' => $phone
                ? Patient::whereRaw($this->normalizedDigitColumn('phone').' = ?', [$phone])->exists()
                : false,
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizePatientRequestDigits($request);
        $requiredFields = json_decode((string) AppSetting::getByKey('patient_required_fields', '{}'), true) ?: [];
        $presence = fn (string $field) => ! empty($requiredFields[$field]) ? 'required' : 'nullable';
        $data = $request->validate([
            'first_name' => $presence('first_name').'|string|max:255',
            'last_name' => $presence('last_name').'|string|max:255',
            'phone' => [
                $presence('phone'),
                'string',
                'max:30',
                Rule::unique('patients', 'phone'),
            ],
            'file_number' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'file_number')],
            'gender' => $presence('gender').'|string|max:20',
            'birth_date' => $presence('birth_date').'|date_format:Y-m-d',
            'area' => $presence('area').'|string|max:255',
            'city' => $presence('city').'|string|max:255',
            'financial_status' => $presence('financial_status').'|string|in:ضعیف,متوسط,خوب,عالی',
            'customer_level' => 'nullable|in:problematic,blue,silver,gold',
            'patient_history' => $presence('patient_history').'|string',
            'medical_history' => $presence('medical_history').'|string',
            'national_id' => $presence('national_id').'|string|max:20',
            'foreign_national_code' => $presence('foreign_national_code').'|string|max:30',
            'father_name' => $presence('father_name').'|string|max:255',
            'marriage_date' => $presence('marriage_date').'|string|max:20',
            'education' => $presence('education').'|string|max:255',
            'second_phone' => $presence('second_phone').'|string|max:30',
            'address' => $presence('address').'|string',
        ], [
            'phone.unique' => 'شماره موبایل تکراری است',
            'file_number.unique' => 'این شماره پرونده قبلاً ثبت شده است.',
        ]);

        // شماره پیشنهادی خودکار است، اما کاربر می‌تواند شماره آزاد دیگری
        // انتخاب کند. قید unique دیتابیس نیز جلوی تداخل درخواست‌های هم‌زمان را می‌گیرد.
        $requestedFileNumber = trim((string) ($data['file_number'] ?? ''));
        $data['customer_level'] = 'silver';
        $patient = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['file_number'] = $requestedFileNumber !== ''
                ? $requestedFileNumber
                : $this->nextFileNumberValue();

            try {
                $patient = Patient::create($data);
                break;
            } catch (QueryException $exception) {
                // در ثبت هم‌زمان، شمارهٔ تازه محاسبه و دوباره امتحان می‌شود.
                $fileNumberCollision = str_contains(strtolower($exception->getMessage()), 'file_number');
                if ($fileNumberCollision && $requestedFileNumber !== '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'file_number' => 'این شماره پرونده قبلاً ثبت شده است.',
                    ]);
                }
                if (! $fileNumberCollision || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        return response()->json([
            'message' => 'پرونده با موفقیت ثبت شد',
            'patient' => $this->hidePatientPhones($patient, $request)
        ], 201);
    }

    public function findByPhone(Request $request, $phone, CustomerLevelService $levels)
    {
        $phone = $this->normalizeDigits($phone);
        $patient = Patient::whereRaw($this->normalizedDigitColumn('phone').' = ?', [$phone])->first();

        if (!$patient) {
            return response()->json(null, 404);
        }

        $levels->decorate(collect([$patient]));
        return response()->json([
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'phone' => PatientPhoneVisibility::hideValue($patient->phone, $request),
            'file_number' => $patient->file_number,
            'gender' => $patient->gender,
            'profile_thumbnail_url' => $patient->profile_thumbnail_url,
            'avatar_url' => $patient->avatar_url,
            'profile_photo_url' => $patient->profile_photo_url,
            'wallet_balance' => $patient->wallet_balance,
            'outstanding_debt' => $patient->outstanding_debt,
            'customer_level' => $patient->customer_level,
        ]);
    }

    public function search(Request $request, CustomerLevelService $levels)
    {
        $query = Patient::query();

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $digitTerm = $this->normalizeDigits($term);
            $query->where(function ($inner) use ($term, $digitTerm) {
                $inner->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"])
                    ->orWhereRaw($this->normalizedDigitColumn('file_number').' LIKE ?', ["%{$digitTerm}%"])
                    ->orWhereRaw($this->normalizedDigitColumn('national_id').' LIKE ?', ["%{$digitTerm}%"])
                    ->orWhereRaw($this->normalizedDigitColumn('phone').' LIKE ?', ["%{$digitTerm}%"]);
            });
        }

        if ($request->filled('file_number')) {
            $value = $this->normalizeDigits($request->file_number);
            $query->whereRaw($this->normalizedDigitColumn('file_number').' = ?', [$value]);
        }

        if ($request->filled('phone')) {
            $value = $this->normalizeDigits($request->phone);
            $query->whereRaw($this->normalizedDigitColumn('phone').' = ?', [$value]);
        }

        if ($request->filled('national_id')) {
            $value = $this->normalizeDigits($request->national_id);
            $query->whereRaw($this->normalizedDigitColumn('national_id').' = ?', [$value]);
        }

        $patients = $levels->decorate($query->limit(25)->get());

        return response()->json($this->hidePatientPhones($patients, $request));
    }

    public function upcomingBirthdays(Request $request)
    {
        $days = max(0, min(30, (int) $request->query('days', 7)));
        $today = Carbon::today();
        $gregorianDates = collect(range(0, $days))->map(fn ($offset) => $today->copy()->addDays($offset));
        $gregorianMonthDays = $gregorianDates
            ->map(fn (Carbon $date) => $date->format('m-d'))
            ->all();
        $jalaliMonthDays = $gregorianDates
            ->map(fn (Carbon $date) => $this->gregorianToJalaliMonthDay($date))
            ->all();

        $patients = Patient::query()
            ->whereNotNull('birth_date')
            ->where('birth_date', '!=', '')
            ->get(['id', 'first_name', 'last_name', 'gender', 'phone', 'file_number', 'birth_date']);

        return response()->json($patients
            ->map(function (Patient $patient) use ($gregorianMonthDays, $jalaliMonthDays) {
                $birthDate = $this->normalizeDateText($patient->birth_date);
                $monthDay = substr($birthDate, 5, 5);
                if (! in_array($monthDay, $gregorianMonthDays, true) && ! in_array($monthDay, $jalaliMonthDays, true)) {
                    return null;
                }

                return $patient;
            })
            ->filter()
            ->values()
            ->tap(fn ($items) => $this->hidePatientPhones($items, $request)));
    }

    private function normalizeDateText(?string $value): string
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '/' => '-',
        ]);
    }

    private function gregorianToJalaliMonthDay(Carbon $date): string
    {
        $gy = (int) $date->format('Y') - 1600;
        $gm = (int) $date->format('n') - 1;
        $gd = (int) $date->format('j') - 1;
        $gregorianMonthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jalaliMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $days = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        for ($i = 0; $i < $gm; $i += 1) {
            $days += $gregorianMonthDays[$i];
        }

        $year = $gy + 1600;
        if ($gm > 1 && ($year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0))) {
            $days += 1;
        }
        $days += $gd - 79;

        $days %= 12053;
        $days %= 1461;
        if ($days >= 366) {
            $days = ($days - 1) % 365;
        }

        for ($jm = 0; $jm < 11 && $days >= $jalaliMonthDays[$jm]; $jm += 1) {
            $days -= $jalaliMonthDays[$jm];
        }

        return str_pad((string) ($jm + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) ($days + 1), 2, '0', STR_PAD_LEFT);
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $this->normalizePatientRequestDigits($request);

        $requiredFields = json_decode((string) AppSetting::getByKey('patient_required_fields', '{}'), true) ?: [];
        $presence = fn (string $field) => ! empty($requiredFields[$field]) ? 'required' : 'nullable';
        $updates = $request->validate([
            'first_name' => [$presence('first_name'), 'string', 'max:255'],
            'last_name' => [$presence('last_name'), 'string', 'max:255'],
            'phone' => [$presence('phone'), 'string', 'max:30', Rule::unique('patients', 'phone')->ignore($patient->id)],
            'gender' => [$presence('gender'), 'string', 'max:20'],
            'birth_date' => [$presence('birth_date'), 'date_format:Y-m-d'],
            'area' => [$presence('area'), 'string', 'max:255'],
            'city' => [$presence('city'), 'string', 'max:255'],
            'financial_status' => [$presence('financial_status'), 'string', Rule::in(['ضعیف', 'متوسط', 'خوب', 'عالی'])],
            'customer_level' => ['nullable', 'in:problematic,blue,silver,gold'],
            'patient_history' => [$presence('patient_history'), 'string'],
            'medical_history' => [$presence('medical_history'), 'string'],
            'national_id' => [$presence('national_id'), 'string', 'max:20'],
            'foreign_national_code' => [$presence('foreign_national_code'), 'string', 'max:30'],
            'father_name' => [$presence('father_name'), 'string', 'max:255'],
            'marriage_date' => [$presence('marriage_date'), 'string', 'max:20'],
            'education' => [$presence('education'), 'string', 'max:255'],
            'second_phone' => [$presence('second_phone'), 'string', 'max:30'],
            'address' => [$presence('address'), 'string'],
        ], [
            'phone.unique' => 'شماره موبایل تکراری است',
        ]);

        if (! PatientPhoneVisibility::canView($request) || PatientPhoneVisibility::looksMasked($updates['phone'] ?? '')) {
            $updates['phone'] = $patient->phone;
            $updates['second_phone'] = $patient->second_phone;
        }

        $patient->update($updates);

        return response()->json([
            'success' => true
        ]);
    }

    private function normalizePatientRequestDigits(Request $request): void
    {
        $fields = ['file_number', 'phone', 'second_phone', 'national_id', 'foreign_national_code', 'birth_date', 'marriage_date'];
        $normalized = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $value = $this->normalizeDigits($request->input($field));
                // ConvertEmptyStringsToNull runs before this controller. Do
                // not turn those nullable values back into an empty string,
                // because MySQL DATE columns reject ''.
                $normalized[$field] = $value === '' ? null : $value;
            }
        }
        $request->merge($normalized);
        if ($request->filled('birth_date')) {
            $request->merge(['birth_date' => $this->normalizeBirthDateForStorage((string) $request->input('birth_date'))]);
        }
    }

    /** Convert the Persian date-picker value to the Gregorian DATE stored by MySQL. */
    private function normalizeBirthDateForStorage(string $value): string
    {
        $normalized = str_replace('/', '-', $this->normalizeDigits($value));
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $parts)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['birth_date' => 'تاریخ تولد معتبر نیست.']);
        }

        [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        if ($year >= 1700) {
            if (! checkdate($month, $day, $year)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['birth_date' => 'تاریخ تولد معتبر نیست.']);
            }
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $maxDay = $month <= 6 ? 31 : 30;
        if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12 || $day < 1 || $day > $maxDay) {
            throw \Illuminate\Validation\ValidationException::withMessages(['birth_date' => 'تاریخ تولد شمسی معتبر نیست.']);
        }

        $jy = $year + 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8)
            + intdiv(($jy % 33) + 3, 4) + $day
            + ($month < 7 ? ($month - 1) * 31 : (($month - 7) * 30) + 186);
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
        $monthDays = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $monthDays[$gm]; $gm++) $gd -= $monthDays[$gm];

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    private function normalizeDigits(mixed $value): string
    {
        return strtr(trim((string) $value), [
            '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4',
            '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
            '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4',
            '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9',
        ]);
    }

    private function normalizedDigitColumn(string $column): string
    {
        $expression = $column;
        foreach (['۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9', '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9'] as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }
        return $expression;
    }

    private function nextFileNumberValue(): string
    {
        $lastFileNumber = Patient::query()
            ->whereNotNull('file_number')
            ->where('file_number', '!=', '')
            ->selectRaw('MAX(CAST(file_number AS UNSIGNED)) AS last_number')
            ->value('last_number');

        $candidate = max(1, ((int) $lastFileNumber) + 1);

        // بعضی داده‌های قدیمی ممکن است شماره‌های نامنظم داشته باشند؛
        // تا اولین شمارهٔ آزاد جلو می‌رویم تا خطای unique رخ ندهد.
        while (Patient::query()->where('file_number', (string) $candidate)->exists()) {
            $candidate++;
        }

        return (string) $candidate;
    }

    private function hidePatientPhones($patients, Request $request)
    {
        if (PatientPhoneVisibility::canView($request)) {
            return $patients;
        }

        $hideOne = function (Patient $patient) {
            $patient->setAttribute('phone', '');
            $patient->setAttribute('second_phone', '');

            return $patient;
        };

        if ($patients instanceof Patient) {
            return $hideOne($patients);
        }

        return $patients->each($hideOne);
    }

    public function updateCustomerLevel(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'customer_level' => ['nullable', 'in:problematic,blue,silver,gold'],
        ]);

        $patient->update([
            'customer_level' => $data['customer_level'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'patient' => $patient->fresh(),
        ]);
    }

    public function uploadProfilePhoto(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'thumbnail' => 'required|image|mimes:webp|max:30|dimensions:width=50,height=50',
        ]);

        Storage::disk('public')->delete(array_filter([
            $patient->profile_photo_path,
            $patient->profile_thumbnail_path,
        ]));

        $path = $request->file('photo')->store("patients/{$patient->id}/profile", 'public');
        $thumbnailPath = $request->file('thumbnail')->storeAs(
            "patients/{$patient->id}/profile",
            'thumbnail-'.Str::uuid().'.webp',
            'public'
        );
        $patient->update([
            'profile_photo_path' => $path,
            'profile_thumbnail_path' => $thumbnailPath,
        ]);

        $patient = $patient->fresh();

        return response()->json([
            'message' => 'عکس پروفایل با موفقیت ذخیره شد.',
            'patient' => $patient,
            'profile_photo_url' => $patient->profile_photo_url,
            'profile_thumbnail_url' => $patient->profile_thumbnail_url,
        ]);
    }

    public function depositWallet(Request $request, $id)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
            'allocations' => 'nullable|array|max:50',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:1',
            'allocations.*.section' => 'nullable|string|max:255',
            'allocations.*.subsection' => 'nullable|string|max:255',
            'allocations.*.service' => 'required_with:allocations|string|max:255',
            'allocations.*.parent_service' => 'nullable|string|max:255',
            'services' => 'nullable|array|max:50',
            'services.*.section' => 'nullable|string|max:255',
            'services.*.subsection' => 'nullable|string|max:255',
            'services.*.service' => 'required_with:services|string|max:255',
            'services.*.amount' => 'required_with:services|numeric|min:1',
        ]);

        if (! empty($data['allocations'])) {
            $allocationsTotal = collect($data['allocations'])->sum(fn (array $allocation) => (float) $allocation['amount']);
            if (round($allocationsTotal, 2) !== round((float) $data['amount'], 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocations' => ['جمع بیعانهٔ زیرخدمت‌ها باید با مبلغ کل برابر باشد.'],
                ]);
            }
        }

        if (! empty($data['services'])) {
            $servicesTotal = collect($data['services'])->sum(fn (array $service) => (float) $service['amount']);
            if (round($servicesTotal, 2) !== round((float) $data['amount'], 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'services' => ['جمع بیعانهٔ خدمات باید با مبلغ کل برابر باشد.'],
                ]);
            }
        }

        $patient = Patient::findOrFail($id);

        DB::transaction(function () use ($patient, $data, $request) {
            $allocations = collect($data['allocations'] ?? []);
            if ($allocations->isNotEmpty()) {
                $allocations->each(function (array $allocation) use ($patient, $data, $request) {
                    $service = [
                        'section' => trim((string) ($allocation['section'] ?? '')),
                        'subsection' => trim((string) ($allocation['subsection'] ?? '')),
                        'service' => trim((string) ($allocation['service'] ?? '')),
                        'parent_service' => trim((string) ($allocation['parent_service'] ?? '')),
                        'amount' => (float) $allocation['amount'],
                    ];
                    $patient->walletTransactions()->create([
                        'amount' => $allocation['amount'],
                        'type' => 'deposit',
                        'description' => 'بیعانه '.$service['service'],
                        'source_type' => 'booking_deposit',
                        'created_by' => $request->user()?->id,
                        'metadata' => ['ip' => $request->ip(), 'services' => [$service]],
                    ]);
                });

                return;
            }

            $patient->walletTransactions()->create([
                'amount' => $data['amount'],
                'type' => 'deposit',
                'description' => $data['description'] ?? 'واریز به کیف پول',
                'source_type' => ! empty($data['services']) ? 'booking_deposit' : 'manual',
                'created_by' => $request->user()?->id,
                'metadata' => [
                    'ip' => $request->ip(),
                    'services' => collect($data['services'] ?? [])->map(fn (array $service) => [
                        'section' => trim((string) ($service['section'] ?? '')),
                        'subsection' => trim((string) ($service['subsection'] ?? '')),
                        'service' => trim((string) ($service['service'] ?? '')),
                        'amount' => (float) ($service['amount'] ?? 0),
                    ])->values()->all(),
                ],
            ]);
        });

        $patient->refresh();

        return response()->json([
            'success' => true,
            'message' => 'مبلغ با موفقیت به کیف پول واریز شد.',
            'wallet_balance' => $patient->wallet_balance // موجودی جدید را برمی‌گردانیم
        ]);
    }

    // متد برداشت از کیف پول
    public function withdrawWallet(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $patient = Patient::findOrFail($id);

        // چک کردن موجودی در سمت سرور (امنیت اصلی کار اینجاست)
        if ($patient->wallet_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'موجودی کیف پول بیمار برای این برداشت کافی نیست.'
            ], 422);
        }

        $transaction = $patient->walletTransactions()->create([
            'amount' => $request->amount,
            'type' => 'withdraw',
            'description' => $request->description ?? 'برداشت از کیف پول',
            'source_type' => 'manual',
            'created_by' => $request->user()?->id,
            'metadata' => ['ip' => $request->ip()],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'مبلغ با موفقیت از کیف پول برداشت شد.',
            'wallet_balance' => $patient->wallet_balance
        ]);
    }

    public function walletTransactions(Patient $patient)
    {
        return response()->json([
            'wallet_balance' => $patient->wallet_balance,
            'transactions' => $patient->walletTransactions()
                ->with('createdBy:id,name')
                ->latest('id')
                ->limit(250)
                ->get()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'description' => $transaction->description,
                    'source_type' => $transaction->source_type,
                    'source_key' => $transaction->source_key,
                    'appointment_id' => $transaction->appointment_id,
                    'reversed_transaction_id' => $transaction->reversed_transaction_id,
                    'reversed_at' => $transaction->reversed_at,
                    'metadata' => $transaction->metadata,
                    'created_by_name' => $transaction->createdBy?->name,
                    'created_at' => $transaction->created_at,
                ]),
        ]);
    }

    public function deleteBookingDeposit(Request $request, Patient $patient, WalletTransaction $transaction)
    {
        if ((int) $transaction->patient_id !== (int) $patient->id || $transaction->type !== 'deposit' || $transaction->source_type !== 'booking_deposit' || $transaction->reversed_at) {
            abort(404);
        }

        try {
            $balance = DB::transaction(function () use ($request, $patient, $transaction) {
                $original = WalletTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                $lockedPatient = Patient::query()->lockForUpdate()->findOrFail($patient->id);

                if ($original->reversed_at) {
                    throw new \RuntimeException('این بیعانه پیش‌تر حذف شده است.');
                }
                if ((float) $lockedPatient->wallet_balance < (float) $original->amount) {
                    throw new \RuntimeException('این بیعانه قبلاً مصرف شده و امکان حذف آن وجود ندارد.');
                }

                $reverse = $lockedPatient->walletTransactions()->create([
                    'type' => 'withdraw',
                    'amount' => $original->amount,
                    'description' => 'حذف بیعانه: '.$original->description,
                    'source_type' => 'reversal',
                    'source_key' => 'booking-deposit-delete-'.$original->id.'-'.now()->format('YmdHisv'),
                    'reversed_transaction_id' => $original->id,
                    'created_by' => $request->user()?->id,
                    'metadata' => ['reason' => 'حذف بیعانه خدمات', 'original' => $original->metadata],
                ]);
                $original->update(['reversed_at' => now(), 'reversed_transaction_id' => $reverse->id]);

                return $lockedPatient->fresh()->wallet_balance;
            });
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'wallet_balance' => $balance]);
    }
}
