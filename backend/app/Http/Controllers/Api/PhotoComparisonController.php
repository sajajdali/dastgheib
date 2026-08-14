<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventorySection;
use App\Models\PatientMedia;
use App\Support\PatientPhoneVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PhotoComparisonController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:150',
            'gender' => 'nullable|string|in:male,female',
            'age_group' => 'nullable|string|in:young,old',
            'angle' => 'nullable|string|max:60',
            'service_id' => 'nullable|integer|exists:inventory_sections,id',
            'tag' => 'nullable|string|max:120',
            'date_from' => 'nullable|string|max:20',
            'date_to' => 'nullable|string|max:20',
            'only_complete' => 'nullable|boolean',
            'consented_only' => 'nullable|boolean',
            'featured' => 'nullable|string|in:featured,regular',
        ]);

        $query = PatientMedia::query()
            ->with(['patient:id,first_name,last_name,phone,file_number,gender,birth_date', 'folder.inventorySection:id,name'])
            ->where('media_type', 'image')
            ->whereNotNull('comparison_stage')
            ->whereNotNull('photo_angle_key')
            ->whereHas('folder', fn ($q) => $q->whereIn('folder_type', ['before_photo', 'after_photo']));

        if ($request->filled('gender')) {
            $query->whereHas('patient', fn ($q) => $q->where('gender', $data['gender']));
        }
        if ($request->filled('angle')) {
            $query->where('photo_angle_key', $data['angle']);
        }
        if (($data['featured'] ?? null) === 'featured') {
            $query->where('is_featured', true);
        } elseif (($data['featured'] ?? null) === 'regular') {
            $query->where('is_featured', false);
        }
        if ($request->filled('date_from')) {
            $query->whereHas('folder', fn ($q) => $q->where('folder_date', '>=', $data['date_from']));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('folder', fn ($q) => $q->where('folder_date', '<=', $data['date_to']));
        }
        if ($request->filled('q')) {
            $term = $data['q'];
            $query->whereHas('patient', function ($q) use ($term) {
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('file_number', 'like', "%{$term}%");
            });
        }

        $media = $query->latest()->get();

        if ($request->filled('age_group')) {
            $media = $media
                ->filter(fn (PatientMedia $item) => $this->matchesAgeGroup($item->patient?->birth_date, $data['age_group']))
                ->values();
        }

        $groups = $media
            ->groupBy(fn (PatientMedia $media) => implode('|', [
                $media->patient_id,
                $media->folder?->folder_date,
                $media->folder?->inventory_section_id,
                $media->photo_angle_key,
            ]))
            ->map(fn (Collection $rows) => $this->comparison($rows, $request))
            ->when($request->filled('service_id'), function (Collection $rows) use ($data) {
                return $rows->filter(function ($row) use ($data) {
                    return (int) ($row['service']?->id ?? 0) === (int) $data['service_id']
                        || collect($row['tags'])->contains(fn ($tag) => (int) ($tag['id'] ?? 0) === (int) $data['service_id']);
                });
            })
            ->when($request->filled('tag'), function (Collection $rows) use ($data) {
                $tag = mb_strtolower(trim($data['tag']));

                return $rows->filter(fn ($row) => collect($row['tags'])->contains(
                    fn ($item) => str_contains(mb_strtolower((string) ($item['name'] ?? '')), $tag)
                ));
            })
            ->when($request->boolean('only_complete'), fn (Collection $rows) => $rows->filter(fn ($row) => $row['before'] && $row['after']))
            ->when($request->boolean('consented_only'), fn (Collection $rows) => $rows->filter(
                fn ($row) => ($row['before']?->usage_consent !== false) && ($row['after']?->usage_consent !== false)
            ))
            ->sortByDesc('sort_date')
            ->values();

        return response()->json([
            'comparisons' => $groups,
            'services' => InventorySection::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'tags' => PatientMedia::query()
                ->whereNotNull('services')
                ->pluck('services')
                ->flatMap(fn ($items) => is_array($items) ? $items : [])
                ->filter(fn ($item) => is_array($item) && ! empty($item['name']))
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    private function comparison(Collection $rows, Request $request): array
    {
        /** @var PatientMedia $sample */
        $sample = $rows->first();
        $before = $rows->firstWhere('comparison_stage', 'before');
        $after = $rows->firstWhere('comparison_stage', 'after');
        $tags = $rows->flatMap(fn (PatientMedia $media) => $media->services ?: [])
            ->filter(fn ($tag) => is_array($tag))
            ->unique(fn ($tag) => $tag['id'] ?? $tag['key'] ?? $tag['name'] ?? serialize($tag))
            ->values();

        $patient = $sample->patient;
        if ($patient && ! PatientPhoneVisibility::canView($request)) {
            $patient->setAttribute('phone', PatientPhoneVisibility::mask($patient->phone));
        }
        $patientData = $patient?->only(['id', 'first_name', 'last_name', 'phone', 'file_number', 'gender']);

        return [
            'key' => implode('-', [$sample->patient_id, $sample->folder?->folder_date, $sample->folder?->inventory_section_id, $sample->photo_angle_key]),
            'patient' => $patientData,
            'date' => $sample->folder?->folder_date,
            'sort_date' => max($before?->created_at?->timestamp ?? 0, $after?->created_at?->timestamp ?? 0),
            'service' => $sample->folder?->inventorySection,
            'tags' => $tags,
            'angle_key' => $sample->photo_angle_key,
            'angle_label' => $sample->photo_angle_label,
            'angle_degrees' => $sample->photo_angle_degrees,
            'is_featured' => $rows->contains(fn (PatientMedia $media) => (bool) $media->is_featured),
            'before' => $before,
            'after' => $after,
        ];
    }

    private function matchesAgeGroup(?string $birthDate, string $group): bool
    {
        $age = $this->ageFromBirthDate($birthDate);

        if ($age === null) {
            return false;
        }

        return $group === 'old' ? $age >= 40 : $age < 40;
    }

    private function ageFromBirthDate(?string $birthDate): ?int
    {
        $normalized = strtr(trim((string) $birthDate), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '/' => '-',
        ]);

        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $parts)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', array_slice($parts, 1));

        try {
            if ($year >= 1200 && $year < 1700) {
                [$year, $month, $day] = $this->jalaliToGregorian($year, $month, $day);
            }

            $birthDate = Carbon::createSafe($year, $month, $day, 0, 0, 0, 'Asia/Tehran');
            $now = now('Asia/Tehran');

            return $birthDate->isFuture() ? null : (int) $birthDate->diffInYears($now);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{int, int, int} */
    private function jalaliToGregorian(int $year, int $month, int $day): array
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new \InvalidArgumentException('Invalid Jalali date.');
        }

        $jy = $year + 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4)
            + $day + ($month < 7 ? (($month - 1) * 31) : ((($month - 7) * 30) + 186));
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $monthDays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $isLeapYear = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        for ($gm = 1; $gm <= 12; $gm++) {
            $daysInMonth = $monthDays[$gm] + ($gm === 2 && $isLeapYear ? 1 : 0);
            if ($gd <= $daysInMonth) {
                return [$gy, $gm, $gd];
            }
            $gd -= $daysInMonth;
        }

        throw new \InvalidArgumentException('Invalid converted date.');
    }
}
