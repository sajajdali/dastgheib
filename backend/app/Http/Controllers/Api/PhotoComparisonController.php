<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventorySection;
use App\Models\PatientMedia;
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
            ->with(['patient:id,first_name,last_name,phone,file_number,gender', 'folder.inventorySection:id,name'])
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
        if ($request->filled('age_group')) {
            $query->where('age_group', $data['age_group']);
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

        $groups = $query->latest()->get()
            ->groupBy(fn (PatientMedia $media) => implode('|', [
                $media->patient_id,
                $media->folder?->folder_date,
                $media->folder?->inventory_section_id,
                $media->photo_angle_key,
            ]))
            ->map(fn (Collection $rows) => $this->comparison($rows))
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

    private function comparison(Collection $rows): array
    {
        /** @var PatientMedia $sample */
        $sample = $rows->first();
        $before = $rows->firstWhere('comparison_stage', 'before');
        $after = $rows->firstWhere('comparison_stage', 'after');
        $tags = $rows->flatMap(fn (PatientMedia $media) => $media->services ?: [])
            ->filter(fn ($tag) => is_array($tag))
            ->unique(fn ($tag) => $tag['id'] ?? $tag['key'] ?? $tag['name'] ?? serialize($tag))
            ->values();

        return [
            'key' => implode('-', [$sample->patient_id, $sample->folder?->folder_date, $sample->folder?->inventory_section_id, $sample->photo_angle_key]),
            'patient' => $sample->patient,
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
}
