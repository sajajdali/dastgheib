<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HumanResourceController;
use App\Models\AppSetting;
use App\Models\BeautyAnnotation;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Services\CustomerLevelService;
use App\Support\PatientPhoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BeautyAnnotationController extends Controller
{
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
        $areas = collect(app(HumanResourceController::class)->serviceTags())
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'areas' => $areas,
            'problems' => $this->faceProblems(),
        ]);
    }

    public function problems()
    {
        return response()->json(['problems' => $this->faceProblems()]);
    }

    public function saveProblems(Request $request)
    {
        $validated = $request->validate([
            'problems' => ['present', 'array', 'max:200'],
            'problems.*' => ['string', 'max:160'],
        ]);

        $problems = collect($validated['problems'])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        AppSetting::updateOrCreate(
            ['key' => 'beauty_face_problems'],
            ['value' => json_encode($problems, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['problems' => $problems]);
    }

    private function faceProblems(): array
    {
        $value = AppSetting::getByKey('beauty_face_problems');
        $stored = json_decode((string) $value, true);

        return collect($value !== null && is_array($stored) ? $stored : self::DEFAULT_PROBLEMS)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function index(Request $request, CustomerLevelService $levels)
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
        $annotations = $query->limit(100)->get();
        // سطح مشتری ذخیره نمی‌شود و باید مثل بقیهٔ صفحه‌ها زنده محاسبه شود.
        $levels->decorate($annotations->pluck('patient')->filter()->unique('id')->values());

        return response()->json([
            'patient_count' => $patientCount,
            'annotations' => $annotations->map(fn (BeautyAnnotation $annotation) => [
                ...$this->formatAnnotation($annotation),
                'patient' => $this->hidePatientPhones($annotation->patient, $request),
                'media' => $annotation->media,
            ]),
        ]);
    }
    public function show(Request $request, Patient $patient, CustomerLevelService $levels)
    {
        // New beauty points are placed only on the patient's profile photo.
        $profilePhoto = $this->profilePhotoPayload($patient);

        // Older points were saved on regular patient photos. Those photos stay
        // reachable (only the ones that actually carry points) so the saved
        // points keep loading; they are not offered as new annotation targets.
        $legacyMediaIds = BeautyAnnotation::query()
            ->where('patient_id', $patient->id)
            ->where('photo_source', BeautyAnnotation::SOURCE_MEDIA)
            ->whereNotNull('patient_media_id')
            ->distinct()
            ->pluck('patient_media_id');

        $legacyPhotos = $legacyMediaIds->isEmpty()
            ? collect()
            : PatientMedia::query()
                ->where('patient_id', $patient->id)
                ->where('media_type', 'image')
                ->whereIn('id', $legacyMediaIds)
                ->latest()
                ->get()
                ->map(fn (PatientMedia $media) => [...$media->toArray(), 'source' => BeautyAnnotation::SOURCE_MEDIA])
                ->values();

        $requestedMediaId = $request->integer('media_id');
        $selectedPhoto = ($requestedMediaId ? $legacyPhotos->firstWhere('id', $requestedMediaId) : null)
            ?: $profilePhoto;

        $annotations = collect();
        if ($selectedPhoto) {
            $annotationsQuery = BeautyAnnotation::query()
                ->with('creator:id,name')
                ->where('patient_id', $patient->id)
                ->latest();

            if ($selectedPhoto['source'] === BeautyAnnotation::SOURCE_PROFILE) {
                $annotationsQuery->where('photo_source', BeautyAnnotation::SOURCE_PROFILE);
            } else {
                $annotationsQuery
                    ->where('photo_source', BeautyAnnotation::SOURCE_MEDIA)
                    ->where('patient_media_id', $selectedPhoto['id']);
            }

            if ($request->filled('date_from')) {
                $annotationsQuery->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $annotationsQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $annotations = $annotationsQuery->get();
        }

        $levels->decorate(collect([$patient]));

        return response()->json([
            'patient' => $this->hidePatientPhones($patient, $request),
            'profile_photo' => $profilePhoto,
            'legacy_photos' => $legacyPhotos,
            'selected_photo' => $selectedPhoto,
            'annotations' => $annotations->map(fn (BeautyAnnotation $annotation) => $this->formatAnnotation($annotation))->values(),
        ]);
    }

    private function profilePhotoPayload(Patient $patient): ?array
    {
        if (! $patient->profile_photo_path) {
            return null;
        }

        return [
            'id' => BeautyAnnotation::SOURCE_PROFILE,
            'source' => BeautyAnnotation::SOURCE_PROFILE,
            'url' => $patient->profile_photo_url,
            'thumbnail_url' => $patient->profile_thumbnail_url,
            'photo_angle_label' => 'عکس پروفایل',
            'created_at' => optional($patient->updated_at)->toDateTimeString(),
        ];
    }

    private function hidePatientPhones(?Patient $patient, Request $request): ?Patient
    {
        if (! $patient || PatientPhoneVisibility::canView($request)) {
            return $patient;
        }

        $patient->setAttribute('phone', '');
        $patient->setAttribute('second_phone', '');

        return $patient;
    }

    public function store(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'photo_source' => 'nullable|in:'.BeautyAnnotation::SOURCE_MEDIA.','.BeautyAnnotation::SOURCE_PROFILE,
            'patient_media_id' => 'required_unless:photo_source,'.BeautyAnnotation::SOURCE_PROFILE.'|nullable|integer|exists:patient_media,id',
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

        $data['photo_source'] = $data['photo_source'] ?? BeautyAnnotation::SOURCE_MEDIA;

        if ($data['photo_source'] === BeautyAnnotation::SOURCE_PROFILE) {
            if (! $patient->profile_photo_path) {
                return response()->json([
                    'message' => 'برای ثبت نقطه، ابتدا عکس پروفایل بیمار را آپلود کنید.',
                ], 422);
            }
            $data['patient_media_id'] = null;
        } else {
            PatientMedia::query()
                ->where('patient_id', $patient->id)
                ->where('media_type', 'image')
                ->findOrFail($data['patient_media_id']);
        }

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
            'photo_source' => $annotation->photo_source ?: BeautyAnnotation::SOURCE_MEDIA,
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
