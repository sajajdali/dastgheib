<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
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
        $fileNumber = $request->query('file_number');
        $phone = $request->query('phone');

        return response()->json([
            'file_number_exists' => $fileNumber
                ? Patient::where('file_number', $fileNumber)->exists()
                : false,

            'phone_exists' => $phone
                ? Patient::where('phone', $phone)->exists()
                : false,
        ]);
    }

    public function store(Request $request)
    {
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
            'gender' => $presence('gender').'|string|max:20',
            'birth_date' => $presence('birth_date').'|string|max:20',
            'area' => $presence('area').'|string|max:255',
            'city' => $presence('city').'|string|max:255',
            'financial_status' => $presence('financial_status').'|string|max:255',
            'customer_level' => 'nullable|in:problematic,blue,silver,gold',
            'patient_history' => $presence('patient_history').'|string',
            'medical_history' => $presence('medical_history').'|string',
            'national_id' => $presence('national_id').'|string|max:20',
            'father_name' => $presence('father_name').'|string|max:255',
            'marriage_date' => $presence('marriage_date').'|string|max:20',
            'education' => $presence('education').'|string|max:255',
            'second_phone' => $presence('second_phone').'|string|max:30',
            'address' => $presence('address').'|string',
        ], [
            'phone.unique' => 'شماره موبایل تکراری است',
        ]);

        // شماره پرونده فقط در سرور صادر می‌شود تا قابل تغییر یا تکرار نباشد.
        // هر پرونده تازه از سطح عادی/نقره‌ای شروع می‌کند.
        $data['customer_level'] = 'silver';
        $patient = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['file_number'] = $this->nextFileNumberValue();

            try {
                $patient = Patient::create($data);
                break;
            } catch (QueryException $exception) {
                // در ثبت هم‌زمان، شمارهٔ تازه محاسبه و دوباره امتحان می‌شود.
                if (! str_contains(strtolower($exception->getMessage()), 'file_number') || $attempt === 4) {
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
        $patient = Patient::where('phone', $phone)->first();

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
            $query->where(function ($inner) use ($term) {
                $inner->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"])
                    ->orWhere('file_number', 'like', "%{$term}%")
                    ->orWhere('national_id', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($request->filled('file_number')) {
            $query->where('file_number', $request->file_number);
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->filled('national_id')) {
            $query->where('national_id', $request->national_id);
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

        $updates = [
            'first_name'       => $request->first_name,
            'last_name'        => $request->last_name,
            'phone'            => $request->phone,
            'gender'           => $request->gender,
            'birth_date'       => $request->birth_date,
            'area'             => $request->area,
            'city'             => $request->city,
            'financial_status' => $request->financial_status,
            'customer_level'   => $request->customer_level,
            'patient_history'  => $request->patient_history,
            'medical_history'  => $request->medical_history,
        ];

        if (! PatientPhoneVisibility::canView($request) || PatientPhoneVisibility::looksMasked($updates['phone'] ?? '')) {
            $updates['phone'] = $patient->phone;
        }

        $patient->update($updates);

        return response()->json([
            'success' => true
        ]);
    }

    private function nextFileNumberValue(): string
    {
        $lastFileNumber = Patient::query()
            ->whereNotNull('file_number')
            ->where('file_number', '!=', '')
            ->orderByRaw('CAST(file_number AS UNSIGNED) DESC')
            ->value('file_number');

        return (string) (((int) $lastFileNumber) + 1);
    }

    private function hidePatientPhones($patients, Request $request)
    {
        if (PatientPhoneVisibility::canView($request)) {
            return $patients;
        }

        $hideOne = function (Patient $patient) {
            $patient->setAttribute('phone', PatientPhoneVisibility::mask($patient->phone));
            $patient->setAttribute('second_phone', PatientPhoneVisibility::mask($patient->second_phone));

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
        $request->validate([
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
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255'
        ]);

        $patient = Patient::findOrFail($id);

        $transaction = $patient->walletTransactions()->create([
            'amount' => $request->amount,
            'type' => 'deposit',
            'description' => $request->description ?? 'واریز به کیف پول',
            'source_type' => 'manual',
            'created_by' => $request->user()?->id,
            'metadata' => ['ip' => $request->ip()],
        ]);

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
}
