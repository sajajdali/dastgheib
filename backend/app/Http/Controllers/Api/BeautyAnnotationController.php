<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeautyAnnotation;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\PatientMedia;
use Illuminate\Http\Request;

class BeautyAnnotationController extends Controller
{
    private const DEFAULT_AREAS = [
        'لب',
        'بینی',
        'زاویه فک',
        'چانه',
        'گونه',
        'پیشانی',
        'خط اخم',
        'غبغب',
        'مو',
        'شقیقه',
        'خط لبخند',
        'اطراف چشم',
        'اطراف لب',
        'ماریونت',
        'ابرو',
        'کل صورت',
        'بدن',
        'گوش',
    ];

    private const DEFAULT_PROBLEMS = [
        'تیرگی',
        'لک',
        'منافذ باز',
        'چروک',
        'افتادگی',
        'جوش',
        'جای جوش',
        'رشد مو',
        'فرم دهی',
        'شلی',
        'پوست خشک',
        'پوست چرب',
        'چربی سوزی',
        'رفع تعریق',
    ];

    public function context()
    {
        $areas = InventorySection::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name')
            ->filter()
            ->merge(self::DEFAULT_AREAS)
            ->unique()
            ->values();

        $problems = Inventory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'service_tags'])
            ->flatMap(function (Inventory $item) {
                return collect($item->service_tags ?: [])
                    ->push($item->name)
                    ->filter();
            })
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->merge(self::DEFAULT_PROBLEMS)
            ->unique()
            ->values();

        return response()->json([
            'areas' => $areas,
            'problems' => $problems,
        ]);
    }

    public function index(Request $request)
    {
        $applyFilters = function ($query) use ($request) {
            $query->where('status', 'pending');

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('annotation_date')) {
                $query->whereDate('annotation_date', $request->annotation_date);
            }
        };

        $latestPendingPerPatient = BeautyAnnotation::query()
            ->selectRaw('MAX(id)')
            ->groupBy('patient_id');
        $applyFilters($latestPendingPerPatient);

        $query = BeautyAnnotation::query()
            ->with([
                'patient:id,first_name,last_name,gender,phone,file_number,customer_level,profile_photo_path,profile_thumbnail_path',
                'media:id,patient_id,path,photo_angle_label,comparison_stage,created_at',
                'creator:id,name',
            ])
            ->whereIn('id', $latestPendingPerPatient)
            ->latest();

        $patientCount = (clone $query)->count();

        return response()->json([
            'patient_count' => $patientCount,
            'annotations' => $query->limit(100)->get()->map(fn (BeautyAnnotation $annotation) => [
                'id' => $annotation->id,
                'patient_id' => $annotation->patient_id,
                'patient_media_id' => $annotation->patient_media_id,
                'area' => $annotation->area,
                'problem' => $annotation->problem,
                'note' => $annotation->note,
                'status' => $annotation->status,
                'annotation_date' => optional($annotation->annotation_date)?->toDateString(),
                'created_at' => optional($annotation->created_at)->toDateTimeString(),
                'patient' => $annotation->patient,
                'media' => $annotation->media,
                'created_by_name' => $annotation->creator?->name,
            ]),
        ]);
    }
    public function show(Request $request, Patient $patient)
    {
        $frontPhotos = PatientMedia::query()
            ->where('patient_id', $patient->id)
            ->where('media_type', 'image')
            ->whereIn('photo_angle_key', [
                'front', 'left_profile', 'right_profile',
                'left_three_quarter_60', 'left_three_quarter_30',
                'right_three_quarter_30', 'right_three_quarter_60',
                'other', 'body_shape',
            ])
            ->latest()
            ->get();

        $selectedPhoto = $frontPhotos
            ->firstWhere('comparison_stage', 'before')
            ?: $frontPhotos->first();

        $selectedPhotoId = $request->integer('media_id') ?: $selectedPhoto?->id;
        if ($selectedPhotoId) {
            $selectedPhoto = $frontPhotos->firstWhere('id', $selectedPhotoId) ?: $selectedPhoto;
        }

        $annotationsQuery = BeautyAnnotation::query()
            ->with('creator:id,name')
            ->where('patient_id', $patient->id)
            ->when($selectedPhoto?->id, fn ($query) => $query->where('patient_media_id', $selectedPhoto->id))
            ->latest();

        if ($request->filled('date_from')) {
            $annotationsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $annotationsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json([
            'patient' => $patient,
            'front_photos' => $frontPhotos,
            'selected_photo' => $selectedPhoto,
            'annotations' => $annotationsQuery->get()->map(fn (BeautyAnnotation $annotation) => [
                'id' => $annotation->id,
                'patient_id' => $annotation->patient_id,
                'patient_media_id' => $annotation->patient_media_id,
                'x_percent' => $annotation->x_percent,
                'y_percent' => $annotation->y_percent,
                'area' => $annotation->area,
                'problem' => $annotation->problem,
                'note' => $annotation->note,
                'status' => $annotation->status,
                'annotation_date' => optional($annotation->annotation_date)?->toDateString(),
                'created_by_name' => $annotation->creator?->name,
                'created_at' => optional($annotation->created_at)->toDateTimeString(),
                'updated_at' => optional($annotation->updated_at)->toDateTimeString(),
            ]),
        ]);
    }

    public function store(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'patient_media_id' => 'required|integer|exists:patient_media,id',
            'x_percent' => 'required|numeric|min:0|max:100',
            'y_percent' => 'required|numeric|min:0|max:100',
            'area' => 'nullable|string|max:120',
            'problem' => 'nullable|string|max:160',
            'note' => 'nullable|string',
            'status' => 'nullable|in:pending,done',
            'annotation_date' => 'nullable|date',
        ]);

        PatientMedia::query()
            ->where('patient_id', $patient->id)
            ->where('media_type', 'image')
            ->findOrFail($data['patient_media_id']);

        $annotation = BeautyAnnotation::create([
            ...$data,
            'patient_id' => $patient->id,
            'created_by' => $request->user()?->id,
            'status' => $data['status'] ?? 'pending',
        ]);

        return response()->json($annotation, 201);
    }

    public function update(Request $request, Patient $patient, BeautyAnnotation $annotation)
    {
        abort_unless($annotation->patient_id === $patient->id, 404);

        $data = $request->validate([
            'x_percent' => 'sometimes|numeric|min:0|max:100',
            'y_percent' => 'sometimes|numeric|min:0|max:100',
            'area' => 'nullable|string|max:120',
            'problem' => 'nullable|string|max:160',
            'note' => 'nullable|string',
            'status' => 'sometimes|in:pending,done',
            'annotation_date' => 'nullable|date',
        ]);

        $annotation->update($data);

        return response()->json($annotation->fresh());
    }

    public function destroy(Patient $patient, BeautyAnnotation $annotation)
    {
        abort_unless($annotation->patient_id === $patient->id, 404);

        $annotation->delete();

        return response()->json(['message' => 'Annotation deleted.']);
    }
}
