<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeautyAnnotation;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Support\PatientPhoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                ...$this->formatAnnotation($annotation),
                'patient' => $this->hidePatientPhones($annotation->patient, $request),
                'media' => $annotation->media,
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
            'patient' => $this->hidePatientPhones($patient, $request),
            'front_photos' => $frontPhotos,
            'selected_photo' => $selectedPhoto,
            'annotations' => $annotationsQuery->get()->map(fn (BeautyAnnotation $annotation) => $this->formatAnnotation($annotation)),
        ]);
    }

    private function hidePatientPhones(?Patient $patient, Request $request): ?Patient
    {
        if (! $patient || PatientPhoneVisibility::canView($request)) {
            return $patient;
        }

        $patient->setAttribute('phone', PatientPhoneVisibility::mask($patient->phone));
        $patient->setAttribute('second_phone', PatientPhoneVisibility::mask($patient->second_phone));

        return $patient;
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
            'voice' => 'nullable|file|mimes:webm,ogg,mp3,wav,m4a,mp4|max:15360',
            'voice_duration' => 'nullable|integer|min:0|max:3600',
            'status' => 'nullable|in:pending,done',
            'annotation_date' => 'nullable|date',
        ]);

        PatientMedia::query()
            ->where('patient_id', $patient->id)
            ->where('media_type', 'image')
            ->findOrFail($data['patient_media_id']);

        $voicePath = $request->hasFile('voice')
            ? $request->file('voice')->store('beauty-annotations/voice', 'public')
            : null;

        unset($data['voice']);

        $annotation = BeautyAnnotation::create([
            ...$data,
            'voice_path' => $voicePath,
            'patient_id' => $patient->id,
            'created_by' => $request->user()?->id,
            'status' => $data['status'] ?? 'pending',
        ]);

        return response()->json($this->formatAnnotation($annotation->fresh()), 201);
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
            'voice' => 'nullable|file|mimes:webm,ogg,mp3,wav,m4a,mp4|max:15360',
            'voice_duration' => 'nullable|integer|min:0|max:3600',
            'status' => 'sometimes|in:pending,done',
            'annotation_date' => 'nullable|date',
        ]);

        if ($request->hasFile('voice')) {
            if ($annotation->voice_path) {
                Storage::disk('public')->delete($annotation->voice_path);
            }
            $data['voice_path'] = $request->file('voice')->store('beauty-annotations/voice', 'public');
        }
        unset($data['voice']);

        $annotation->update($data);

        return response()->json($this->formatAnnotation($annotation->fresh()));
    }

    public function destroy(Patient $patient, BeautyAnnotation $annotation)
    {
        abort_unless($annotation->patient_id === $patient->id, 404);

        if ($annotation->voice_path) {
            Storage::disk('public')->delete($annotation->voice_path);
        }

        $annotation->delete();

        return response()->json(['message' => 'Annotation deleted.']);
    }

    private function formatAnnotation(BeautyAnnotation $annotation): array
    {
        return [
            'id' => $annotation->id,
            'patient_id' => $annotation->patient_id,
            'patient_media_id' => $annotation->patient_media_id,
            'x_percent' => $annotation->x_percent,
            'y_percent' => $annotation->y_percent,
            'area' => $annotation->area,
            'problem' => $annotation->problem,
            'note' => $annotation->note,
            'voice_url' => $annotation->voice_path ? Storage::disk('public')->url($annotation->voice_path) : null,
            'voice_duration' => $annotation->voice_duration,
            'status' => $annotation->status,
            'annotation_date' => optional($annotation->annotation_date)?->toDateString(),
            'created_by_name' => $annotation->creator?->name,
            'created_at' => optional($annotation->created_at)->toDateTimeString(),
            'updated_at' => optional($annotation->updated_at)->toDateTimeString(),
        ];
    }
}
