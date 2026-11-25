<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HumanResourceController;
use App\Models\AppSetting;
use App\Models\BeautyAnnotation;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Models\PatientMediaFolder;
use App\Services\CustomerLevelService;
use App\Support\PatientPhoneVisibility;
use Carbon\Carbon;
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
            'regions' => $this->regionCatalog(),
            'templates' => $this->templateCatalog(),
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

    /**
     * نقاط زیبایار که تاریخ اقدام‌شان گذشته و هنوز «در انتظار» مانده‌اند.
     * هر نقطه یک ردیف است (یک بیمار می‌تواند چند بار ظاهر شود).
     */
    public function overdue(Request $request, CustomerLevelService $levels)
    {
        $today = Carbon::today()->toDateString();
        $baseOverdue = fn () => BeautyAnnotation::query()
            ->where('status', 'pending')
            ->whereNotNull('annotation_date')
            ->whereDate('annotation_date', '<', $today);

        $total = $baseOverdue()->count();

        $creators = $baseOverdue()
            ->whereNotNull('created_by')
            ->with('creator:id,name')
            ->get(['id', 'created_by'])
            ->pluck('creator')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $query = $baseOverdue()->with([
            'patient:id,first_name,last_name,gender,phone,file_number,customer_level,profile_photo_path,profile_thumbnail_path',
            'media:id,patient_id,path,photo_angle_label,comparison_stage,created_at',
            'creator:id,name',
        ]);

        if ($request->filled('date_from')) {
            $query->whereDate('annotation_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('annotation_date', '<=', $request->date_to);
        }
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }
        if ($request->filled('problem')) {
            $query->where('problem', $request->problem);
        }
        if ($request->filled('created_by')) {
            $query->where('created_by', (int) $request->created_by);
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->whereHas('patient', function ($patientQuery) use ($term) {
                $patientQuery->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) like ?", ["%{$term}%"])
                        ->orWhere('file_number', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('second_phone', 'like', "%{$term}%");
                });
            });
        }

        $minDays = $request->filled('overdue_days_min') ? max(1, (int) $request->overdue_days_min) : null;
        $maxDays = $request->filled('overdue_days_max') ? max(1, (int) $request->overdue_days_max) : null;
        if ($minDays !== null) {
            $query->whereDate('annotation_date', '<=', Carbon::today()->subDays($minDays)->toDateString());
        }
        if ($maxDays !== null) {
            $query->whereDate('annotation_date', '>=', Carbon::today()->subDays($maxDays)->toDateString());
        }

        $annotations = $query
            ->orderBy('annotation_date')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $levels->decorate($annotations->pluck('patient')->filter()->unique('id')->values());
        $todayCarbon = Carbon::today();

        return response()->json([
            'total' => $total,
            'count' => $annotations->count(),
            'today' => $today,
            'creators' => $creators,
            'annotations' => $annotations->map(function (BeautyAnnotation $annotation) use ($request, $todayCarbon) {
                $planned = $annotation->annotation_date
                    ? Carbon::parse($annotation->annotation_date)->startOfDay()
                    : null;
                $daysOverdue = $planned ? (int) $planned->diffInDays($todayCarbon) : 0;

                return [
                    ...$this->formatAnnotation($annotation),
                    'days_overdue' => $daysOverdue,
                    'patient' => $this->hidePatientPhones($annotation->patient, $request),
                    'media' => $annotation->media,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, Patient $patient, CustomerLevelService $levels)
    {
        $profilePhoto = $this->profilePhotoPayload($patient);
        $region = BeautyAnnotation::isValidRegion($request->query('region'))
            ? (string) $request->query('region')
            : BeautyAnnotation::REGION_FACE;
        $requestedCanvas = strtolower(trim((string) $request->query('canvas', '')));
        if (! in_array($requestedCanvas, ['photo', 'template', 'media', ''], true)) {
            $requestedCanvas = '';
        }

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
                ->map(fn (PatientMedia $media) => [
                    ...$media->toArray(),
                    'source' => BeautyAnnotation::SOURCE_MEDIA,
                    'region' => BeautyAnnotation::REGION_FACE,
                    'is_template' => false,
                ])
                ->values();

        $requestedMediaId = $request->integer('media_id');
        $legacySelected = $requestedMediaId
            ? $legacyPhotos->firstWhere('id', $requestedMediaId)
            : null;

        $regionCounts = BeautyAnnotation::query()
            ->where('patient_id', $patient->id)
            ->selectRaw('region, photo_source, COUNT(*) as total')
            ->groupBy('region', 'photo_source')
            ->get();

        $regions = collect(BeautyAnnotation::REGIONS)->map(function (string $label, string $key) use ($regionCounts, $profilePhoto) {
            $forRegion = $regionCounts->where('region', $key);
            $photoCount = (int) $forRegion->whereIn('photo_source', [
                BeautyAnnotation::SOURCE_PROFILE,
                BeautyAnnotation::SOURCE_MEDIA,
            ])->sum('total');
            $templateCount = (int) $forRegion->where('photo_source', BeautyAnnotation::SOURCE_TEMPLATE)->sum('total');

            return [
                'key' => $key,
                'label' => $label,
                'point_count' => $photoCount + $templateCount,
                'photo_point_count' => $photoCount,
                'template_point_count' => $templateCount,
                'has_real_photo' => $key === BeautyAnnotation::REGION_FACE && (bool) $profilePhoto,
            ];
        })->values();

        if ($legacySelected) {
            $selectedPhoto = $legacySelected;
            $selectedCanvas = 'media';
            $region = BeautyAnnotation::REGION_FACE;
        } else {
            $canUseRealPhoto = $region === BeautyAnnotation::REGION_FACE && (bool) $profilePhoto;
            if ($requestedCanvas === 'template' || ! $canUseRealPhoto) {
                $selectedPhoto = $this->templatePayload($region);
                $selectedCanvas = 'template';
            } else {
                $selectedPhoto = $profilePhoto;
                $selectedCanvas = 'photo';
            }
        }

        $annotationsQuery = BeautyAnnotation::query()
            ->with('creator:id,name')
            ->where('patient_id', $patient->id)
            ->where('region', $region)
            ->latest();

        if ($selectedCanvas === 'photo') {
            $annotationsQuery->where('photo_source', BeautyAnnotation::SOURCE_PROFILE);
        } elseif ($selectedCanvas === 'template') {
            $annotationsQuery->where('photo_source', BeautyAnnotation::SOURCE_TEMPLATE);
        } else {
            $annotationsQuery
                ->where('photo_source', BeautyAnnotation::SOURCE_MEDIA)
                ->where('patient_media_id', $selectedPhoto['id'] ?? null);
        }

        if ($request->filled('date_from')) {
            $annotationsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $annotationsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $annotations = $annotationsQuery->get();

        $levels->decorate(collect([$patient]));

        return response()->json([
            'patient' => $this->hidePatientPhones($patient, $request),
            'profile_photo' => $profilePhoto,
            'legacy_photos' => $legacyPhotos,
            'regions' => $regions,
            'templates' => $this->templateCatalog(),
            'selected_region' => $region,
            'selected_canvas' => $selectedCanvas,
            'selected_photo' => $selectedPhoto,
            'can_annotate' => in_array($selectedCanvas, ['photo', 'template'], true),
            'annotations' => $annotations->map(fn (BeautyAnnotation $annotation) => $this->formatAnnotation($annotation))->values(),
            'photo_timeline' => $this->photoTimeline($patient),
        ]);
    }

    /**
     * زیر عکس پروفایل زیبایار: اولین عکس «قبل» و آخرین عکس «بعد» برای هر زاویه (برای چرخش هم‌زمان)،
     * و پوشه‌های تاریخ عکس‌های بیمار، جدیدترین اول.
     */
    private function photoTimeline(Patient $patient): array
    {
        $folders = PatientMediaFolder::query()->where('patient_id', $patient->id)
            ->get(['id', 'parent_id', 'name', 'folder_type', 'folder_date', 'created_at'])->keyBy('id');
        $media = PatientMedia::query()->where('patient_id', $patient->id)->where('media_type', 'image')
            ->orderBy('created_at')->orderBy('id')->get();

        $rootOf = function (?int $folderId) use ($folders): ?PatientMediaFolder {
            $folder = $folderId ? $folders->get($folderId) : null;
            for ($guard = 0; $folder && $folder->parent_id && $guard < 10; $guard++) {
                $folder = $folders->get($folder->parent_id) ?? $folder;
            }
            return $folder;
        };
        $stageOf = function (PatientMedia $item) use ($folders): ?string {
            if (in_array($item->comparison_stage, ['before', 'after'], true)) return $item->comparison_stage;
            return match ($folders->get($item->folder_id)?->folder_type) {
                'before_photo' => 'before',
                'after_photo' => 'after',
                default => null,
            };
        };
        // تاریخ هر عکس: تاریخ پوشهٔ روز (شمسی، مثل 1405-08-08)؛ عکس بدون پوشهٔ تاریخ با زمان بارگذاری.
        $items = $media->map(function (PatientMedia $item) use ($rootOf, $stageOf) {
            $root = $rootOf($item->folder_id);
            return [
                'media' => $item,
                'root' => $root,
                'stage' => $stageOf($item),
                'sort' => ($root?->folder_date ?: '0000').'|'.optional($item->created_at)->format('Y-m-d H:i:s').'|'.str_pad((string) $item->id, 10, '0', STR_PAD_LEFT),
            ];
        });
        $photo = fn (?array $entry) => $entry ? [
            'id' => $entry['media']->id,
            'url' => $entry['media']->url,
            'date' => $entry['root']?->folder_date,
            'folder_id' => $entry['root']?->id,
            'created_at' => optional($entry['media']->created_at)->toDateTimeString(),
        ] : null;

        $angles = collect(PatientMediaController::PHOTO_ANGLES)->map(function (array $angle, string $key) use ($items, $photo) {
            $forAngle = $items->filter(fn ($entry) => ($entry['media']->photo_angle_key ?: 'other') === $key)->sortBy('sort');
            $before = $forAngle->where('stage', 'before')->first();
            $after = $forAngle->where('stage', 'after')->last();
            if (! $before && ! $after) return null;
            return ['key' => $key, 'label' => $angle['label'], 'before' => $photo($before), 'after' => $photo($after)];
        })->filter()->values();

        $dateFolders = $folders->filter(fn (PatientMediaFolder $folder) => ! $folder->parent_id)
            ->map(function (PatientMediaFolder $folder) use ($folders, $items) {
                $inside = $items->filter(fn ($entry) => $entry['root']?->id === $folder->id)->sortBy('sort');
                $services = $folders->filter(fn ($child) => (int) $child->parent_id === (int) $folder->id && $child->folder_type === 'service')
                    ->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->values();
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'date' => $folder->folder_date ?: $folder->name,
                    'services' => $services,
                    'photo_count' => $inside->count(),
                    'before_count' => $inside->where('stage', 'before')->count(),
                    'after_count' => $inside->where('stage', 'after')->count(),
                    'cover_url' => $inside->first()['media']->url ?? null,
                ];
            })
            ->sortByDesc(fn (array $folder) => (string) $folder['date'])
            ->values();

        return ['angles' => $angles, 'folders' => $dateFolders];
    }

    private function profilePhotoPayload(Patient $patient): ?array
    {
        if (! $patient->profile_photo_path) {
            return null;
        }

        return [
            'id' => BeautyAnnotation::SOURCE_PROFILE,
            'source' => BeautyAnnotation::SOURCE_PROFILE,
            'region' => BeautyAnnotation::REGION_FACE,
            'url' => $patient->profile_photo_url,
            'thumbnail_url' => $patient->profile_thumbnail_url,
            'photo_angle_label' => 'عکس پروفایل',
            'created_at' => optional($patient->updated_at)->toDateTimeString(),
            'is_template' => false,
        ];
    }

    private function templatePayload(string $region): array
    {
        $region = BeautyAnnotation::isValidRegion($region) ? $region : BeautyAnnotation::REGION_FACE;
        $label = BeautyAnnotation::REGIONS[$region] ?? $region;

        return [
            'id' => 'template:'.$region,
            'source' => BeautyAnnotation::SOURCE_TEMPLATE,
            'region' => $region,
            'url' => '/beauty-templates/'.$region.'.svg',
            'thumbnail_url' => null,
            'photo_angle_label' => 'قالب '.$label,
            'created_at' => null,
            'is_template' => true,
        ];
    }

    private function regionCatalog(): array
    {
        return collect(BeautyAnnotation::REGIONS)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function templateCatalog(): array
    {
        return collect(BeautyAnnotation::regionKeys())
            ->mapWithKeys(fn (string $key) => [$key => $this->templatePayload($key)])
            ->all();
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
        $regionKeys = implode(',', BeautyAnnotation::regionKeys());
        $data = $request->validate([
            'photo_source' => 'nullable|in:'.BeautyAnnotation::SOURCE_MEDIA.','.BeautyAnnotation::SOURCE_PROFILE.','.BeautyAnnotation::SOURCE_TEMPLATE,
            'region' => 'nullable|in:'.$regionKeys,
            'patient_media_id' => 'nullable|integer|exists:patient_media,id',
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

        $data['photo_source'] = $data['photo_source'] ?? BeautyAnnotation::SOURCE_TEMPLATE;
        $data['region'] = BeautyAnnotation::isValidRegion($data['region'] ?? null)
            ? $data['region']
            : BeautyAnnotation::REGION_FACE;

        if ($data['photo_source'] === BeautyAnnotation::SOURCE_PROFILE) {
            if (! $patient->profile_photo_path) {
                return response()->json([
                    'message' => 'برای ثبت نقطه روی عکس واقعی، ابتدا عکس پروفایل بیمار را آپلود کنید. می‌توانید فعلاً روی قالب پیش‌فرض نقطه بگذارید.',
                ], 422);
            }
            // عکس پروفایل فقط برای نمای صورت معنا دارد
            $data['region'] = BeautyAnnotation::REGION_FACE;
            $data['patient_media_id'] = null;
        } elseif ($data['photo_source'] === BeautyAnnotation::SOURCE_TEMPLATE) {
            // نقاط قالب حتی بعد از آپلود عکس پروفایل دست‌نخورده می‌مانند
            $data['patient_media_id'] = null;
        } else {
            if (empty($data['patient_media_id'])) {
                return response()->json([
                    'message' => 'برای نقطه روی عکس پرونده، شناسه عکس لازم است.',
                ], 422);
            }
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

        $regionKeys = implode(',', BeautyAnnotation::regionKeys());
        $data = $request->validate([
            'x_percent' => 'sometimes|numeric|min:0|max:100',
            'y_percent' => 'sometimes|numeric|min:0|max:100',
            'region' => 'sometimes|in:'.$regionKeys,
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
        $source = $annotation->photo_source ?: BeautyAnnotation::SOURCE_MEDIA;
        $region = BeautyAnnotation::isValidRegion($annotation->region)
            ? $annotation->region
            : BeautyAnnotation::REGION_FACE;

        return [
            'id' => $annotation->id,
            'patient_id' => $annotation->patient_id,
            'patient_media_id' => $annotation->patient_media_id,
            'photo_source' => $source,
            'region' => $region,
            'region_label' => BeautyAnnotation::REGIONS[$region] ?? $region,
            'on_template' => $source === BeautyAnnotation::SOURCE_TEMPLATE,
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
