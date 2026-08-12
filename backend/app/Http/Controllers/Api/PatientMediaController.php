<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\PatientMedia;
use App\Models\PatientMediaFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PatientMediaController extends Controller
{
    private const PHOTO_ANGLES = [
        'left_profile' => ['label' => 'نیم‌رخ چپ', 'degrees' => 90],
        'left_three_quarter_60' => ['label' => 'سه‌رخ اول چپ', 'degrees' => 60],
        'left_three_quarter_30' => ['label' => 'سه‌رخ دوم چپ', 'degrees' => 30],
        'front' => ['label' => 'تمام‌رخ', 'degrees' => 0],
        'right_three_quarter_30' => ['label' => 'سه‌رخ اول راست', 'degrees' => 30],
        'right_three_quarter_60' => ['label' => 'سه‌رخ دوم راست', 'degrees' => 60],
        'right_profile' => ['label' => 'نیم‌رخ راست', 'degrees' => 90],
        'other' => ['label' => 'سایر', 'degrees' => 0],
        'body_shape' => ['label' => 'شیپ بدن', 'degrees' => 0],
    ];

    public function index(Request $request, Patient $patient)
    {
        $folderId = $request->query('folder_id');
        $showAll = $request->boolean('all');

        $mediaQuery = PatientMedia::query()
            ->with('uploader:id,name')
            ->where('patient_id', $patient->id)
            ->latest();

        if (! $showAll) {
            $mediaQuery->where('folder_id', $folderId);
        }

        $media = $mediaQuery->get()->map(function (PatientMedia $item) use ($patient) {
            $item->folder_path = $this->folderPath($patient, $item->folder_id);
            $item->uploaded_by_name = $item->uploader?->name;
            return $item;
        });

        $folders = PatientMediaFolder::query()
            ->where('patient_id', $patient->id)
            ->where('parent_id', $folderId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if (! $folderId) {
            $folders = $folders
                ->sortByDesc(fn (PatientMediaFolder $folder) => $this->normalizeJalaliDate($folder->folder_date ?: $folder->name))
                ->values();
        }

        $folders = $this->addConsentWarnings($patient, $folders);

        return response()->json([
            'patient' => $patient->only(['id', 'first_name', 'last_name', 'file_number']),
            'current_folder_id' => $folderId ? (int) $folderId : null,
            'all' => $showAll,
            'breadcrumbs' => $this->breadcrumbs($patient, $folderId),
            'folders' => $folders,
            'media' => $media,
            'sections' => $this->mediaSections(),
            'service_groups' => $this->serviceGroups(),
        ]);
    }

    public function storeFolder(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'type' => 'required|string|in:date,service',
            'date' => 'required_if:type,date|nullable|string|max:20|regex:/^[0-9۰-۹٠-٩]{4}[-\/][0-9۰-۹٠-٩]{2}[-\/][0-9۰-۹٠-٩]{2}$/u',
            'section_id' => 'required_if:type,service|nullable|integer|exists:inventory_sections,id',
            'parent_id' => 'nullable|integer|exists:patient_media_folders,id',
        ]);

        if ($data['type'] === 'date') {
            $dateName = $this->normalizeJalaliDate($data['date']);

            $existingFolder = PatientMediaFolder::query()
                ->where('patient_id', $patient->id)
                ->whereNull('parent_id')
                ->get()
                ->first(fn (PatientMediaFolder $folder) => $this->normalizeJalaliDate($folder->folder_date ?: $folder->name) === $dateName);

            if ($existingFolder) {
                return response()->json([
                    'message' => 'این فولدر از قبل موجود بود و برای شما باز شد.',
                    'folder' => $existingFolder,
                    'already_exists' => true,
                ], 200);
            }

            $folder = PatientMediaFolder::create([
                'patient_id' => $patient->id,
                'parent_id' => null,
                'name' => $dateName,
                'folder_type' => 'date',
                'folder_date' => $dateName,
            ]);

            return response()->json($folder, 201);
        }

        $parent = PatientMediaFolder::query()
            ->where('patient_id', $patient->id)
            ->find($data['parent_id'] ?? null);

        if (! $parent) {
            return response()->json([
                'message' => 'فولدر والد پیدا نشد. گالری را تازه‌سازی کنید و دوباره تلاش کنید.',
            ], 404);
        }

        if ($parent->parent_id !== null || ! in_array($parent->folder_type, [null, 'date'], true)) {
            return response()->json([
                'message' => 'فولدر بخش را فقط داخل فولدر اصلی تاریخ می‌توان ساخت.',
            ], 422);
        }

        if ($parent->folder_type === null) {
            $legacyDate = $this->normalizeJalaliDate($parent->folder_date ?: $parent->name);
            $parent->update([
                'folder_type' => 'date',
                'folder_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacyDate) ? $legacyDate : null,
            ]);
        }

        $section = InventorySection::query()->findOrFail($data['section_id']);

        $existingFolder = PatientMediaFolder::query()
            ->where('patient_id', $patient->id)
            ->where('parent_id', $parent->id)
            ->where('name', $section->name)
            ->first();

        if ($existingFolder) {
            $existingFolder->load('children');

            return response()->json([
                'message' => 'Folder already exists.',
                'folder' => $existingFolder,
                'already_exists' => true,
            ], 200);
            return response()->json(['message' => 'برای این خدمت قبلا فولدر ساخته شده است.'], 422);
        }

        $folder = PatientMediaFolder::create([
            'patient_id' => $patient->id,
            'parent_id' => $parent->id,
            'name' => $section->name,
            'folder_type' => 'service',
            'folder_date' => $parent->folder_date,
            'inventory_section_id' => $section->id,
        ]);

        foreach ([
            ['name' => 'عکس قبل', 'type' => 'before_photo', 'sort' => 1],
            ['name' => 'عکس بعد', 'type' => 'after_photo', 'sort' => 2],
            ['name' => 'ویدیوها', 'type' => 'videos', 'sort' => 3],
        ] as $child) {
            PatientMediaFolder::create([
                'patient_id' => $patient->id,
                'parent_id' => $folder->id,
                'name' => $child['name'],
                'folder_type' => $child['type'],
                'folder_date' => $parent->folder_date,
                'inventory_section_id' => $section->id,
                'sort_order' => $child['sort'],
            ]);
        }

        $folder->load('children');

        return response()->json($folder, 201);
    }

    public function storeFiles(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'folder_id' => 'nullable|integer|exists:patient_media_folders,id',
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200|mimes:jpg,jpeg,png,webp,gif,heic,heif,mp4,mov,avi,webm',
            'age_group' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'services' => 'nullable',
            'comparison_stage' => 'nullable|string|in:before,after',
            'photo_angle_key' => 'nullable|string|max:60',
            'photo_angle_label' => 'nullable|string|max:120',
            'photo_angle_degrees' => 'nullable|integer|min:0|max:180',
            'usage_consent' => 'nullable|boolean',
        ]);

        $folderId = $data['folder_id'] ?? null;
        $folder = null;
        if ($folderId) {
            $folder = PatientMediaFolder::query()
                ->where('patient_id', $patient->id)
                ->findOrFail($folderId);
        }

        $comparisonStage = $data['comparison_stage'] ?? $this->stageFromFolder($folder);
        $folderStage = $this->stageFromFolder($folder);
        if ($folderStage) {
            $comparisonStage = $folderStage;
            $this->validateComparisonPhoto($request, $folder, $request->file('files', []));
        }

        $angle = $this->canonicalAngle($data['photo_angle_key'] ?? null);

        $services = $request->input('services');
        if (is_string($services)) {
            $services = json_decode($services, true) ?: [];
        }
        if (! is_array($services)) {
            $services = [];
        }
        $services = $this->normalizeServiceTags($services);

        $created = [];
        foreach ($request->file('files', []) as $file) {
            $type = Str::startsWith((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            $path = $file->store("patients/{$patient->id}/media", 'public');

            $created[] = PatientMedia::create([
                'patient_id' => $patient->id,
                'uploaded_by' => $request->user()?->id,
                'folder_id' => $folderId,
                'file_name' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'media_type' => $type,
                'path' => $path,
                'size' => $file->getSize() ?: 0,
                'is_featured' => $request->boolean('is_featured'),
                'usage_consent' => ! $request->has('usage_consent') || $request->boolean('usage_consent'),
                'gender' => null,
                'age_group' => $data['age_group'] ?? null,
                'description' => $data['description'] ?? null,
                'services' => $services,
                'comparison_stage' => $comparisonStage,
                'photo_angle_key' => $angle ? $data['photo_angle_key'] : null,
                'photo_angle_label' => $angle['label'] ?? null,
                'photo_angle_degrees' => $angle['degrees'] ?? null,
            ]);
        }

        return response()->json($created, 201);
    }

    public function update(Request $request, Patient $patient, PatientMedia $media)
    {
        abort_unless($media->patient_id === $patient->id, 404);

        $data = $request->validate([
            'file' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,webp,gif,heic,heif,mp4,mov,avi,webm',
            'is_featured' => 'nullable|boolean',
            'usage_consent' => 'nullable|boolean',
            'age_group' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'services' => 'nullable',
            'comparison_stage' => 'nullable|string|in:before,after',
            'photo_angle_key' => 'nullable|string|max:60',
            'photo_angle_label' => 'nullable|string|max:120',
            'photo_angle_degrees' => 'nullable|integer|min:0|max:180',
        ]);
        unset($data['file']);
        $data['gender'] = null;

        $folder = $media->folder;
        $folderStage = $this->stageFromFolder($folder);
        if ($folderStage) {
            $files = $request->hasFile('file') ? [$request->file('file')] : [];
            $this->validateComparisonPhoto($request, $folder, $files, $media);
            $data['comparison_stage'] = $folderStage;
            $angleKey = $data['photo_angle_key'] ?? $media->photo_angle_key;
            $angle = $this->canonicalAngle($angleKey);
            $data['photo_angle_key'] = $angleKey;
            $data['photo_angle_label'] = $angle['label'];
            $data['photo_angle_degrees'] = $angle['degrees'];
        }

        if ($request->has('services')) {
            $services = $request->input('services');
            if (is_string($services)) {
                $services = json_decode($services, true) ?: [];
            }
            if (! is_array($services)) {
                $services = [];
            }
            $data['services'] = $this->normalizeServiceTags($services);
        }

        if ($request->hasFile('file')) {
            if ($media->path) {
                Storage::disk('public')->delete($media->path);
            }

            $file = $request->file('file');
            $path = $file->store("patients/{$patient->id}/media", 'public');

            $data['file_name'] = basename($path);
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getMimeType();
            $data['media_type'] = Str::startsWith((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            $data['path'] = $path;
            $data['size'] = $file->getSize() ?: 0;
        }

        $media->update($data);

        $fresh = $media->fresh()->load('uploader:id,name');
        $fresh->folder_path = $this->folderPath($patient, $fresh->folder_id);
        $fresh->uploaded_by_name = $fresh->uploader?->name;

        return response()->json($fresh);
    }

    public function destroy(Patient $patient, PatientMedia $media)
    {
        abort_unless($media->patient_id === $patient->id, 404);

        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();

        return response()->json([
            'message' => 'فایل با موفقیت حذف شد.',
        ]);
    }

    public function destroyFolder(Patient $patient, PatientMediaFolder $folder)
    {
        abort_unless($folder->patient_id === $patient->id, 404);

        $folderIds = [$folder->id];
        $pendingIds = [$folder->id];

        while ($pendingIds !== []) {
            $childIds = PatientMediaFolder::query()
                ->where('patient_id', $patient->id)
                ->whereIn('parent_id', $pendingIds)
                ->pluck('id')
                ->all();

            $newIds = array_values(array_diff($childIds, $folderIds));
            if ($newIds === []) {
                break;
            }

            $folderIds = array_merge($folderIds, $newIds);
            $pendingIds = $newIds;
        }

        $media = PatientMedia::query()
            ->where('patient_id', $patient->id)
            ->whereIn('folder_id', $folderIds)
            ->get(['id', 'path']);

        DB::transaction(function () use ($folder, $media) {
            PatientMedia::query()->whereKey($media->pluck('id'))->get()->each->delete();
            $folder->delete();
        });

        $paths = $media->pluck('path')->filter()->values()->all();
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }

        return response()->json([
            'message' => 'فولدر و تمام عکس‌ها و زیرپوشه‌های آن با موفقیت حذف شدند.',
            'deleted_folders' => count($folderIds),
            'deleted_files' => $media->count(),
        ]);
    }

    private function breadcrumbs(Patient $patient, $folderId): array
    {
        $items = [];
        $folder = $folderId
            ? PatientMediaFolder::query()->where('patient_id', $patient->id)->find($folderId)
            : null;

        while ($folder) {
            array_unshift($items, [
                'id' => $folder->id,
                'name' => $folder->name,
                'folder_type' => $folder->folder_type,
                'inventory_section_id' => $folder->inventory_section_id,
            ]);
            $folder = $folder->parent_id
                ? PatientMediaFolder::query()->where('patient_id', $patient->id)->find($folder->parent_id)
                : null;
        }

        return $items;
    }

    private function folderPath(Patient $patient, $folderId): string
    {
        $items = $this->breadcrumbs($patient, $folderId);

        return count($items)
            ? collect($items)->pluck('name')->implode(' / ')
            : 'ریشه';
    }

    private function serviceGroups(): array
    {
        return Inventory::query()
            ->with('section:id,parent_id,level,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'section_id', 'name', 'service_tags'])
            ->groupBy(function (Inventory $item) {
                return (int) ($item->section?->level) > 2 && $item->section?->parent_id
                    ? $item->section->parent_id
                    : $item->section_id;
            })
            ->map(function ($items, $sectionId) {
                $section = InventorySection::query()->find($sectionId);
                $sectionName = $section?->name ?: $items->first()?->section?->name ?: 'بدون بخش';
                $tags = $items
                    ->flatMap(fn (Inventory $item) => collect($item->service_tags ?: []))
                    ->map(fn ($tag) => trim((string) $tag))
                    ->filter()
                    ->unique()
                    ->values()
                    ->map(function (string $tag) use ($sectionId, $sectionName) {
                        $key = 'tag-'.sha1($sectionId.'|'.$tag);

                        return [
                            'id' => $key,
                            'key' => $key,
                            'name' => $tag,
                            'section' => $sectionName,
                            'section_id' => (int) $sectionId,
                            'type' => 'service_tag',
                        ];
                    })
                    ->all();

                return [
                    'section' => $sectionName,
                    'section_id' => (int) $sectionId,
                    'items' => $tags,
                ];
            })
            ->filter(fn (array $group) => count($group['items']) > 0)
            ->values()
            ->all();
    }

    private function mediaSections(): array
    {
        return InventorySection::query()
            ->where('level', '<=', 2)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'level', 'name'])
            ->all();
    }

    private function stageFromFolder(?PatientMediaFolder $folder): ?string
    {
        return match ($folder?->folder_type) {
            'before_photo' => 'before',
            'after_photo' => 'after',
            default => null,
        };
    }

    private function canonicalAngle(?string $key): ?array
    {
        return $key && isset(self::PHOTO_ANGLES[$key]) ? self::PHOTO_ANGLES[$key] : null;
    }

    private function validateComparisonPhoto(
        Request $request,
        PatientMediaFolder $folder,
        array $files,
        ?PatientMedia $currentMedia = null
    ): void {
        $angleKey = $request->input('photo_angle_key', $currentMedia?->photo_angle_key);

        if (! $this->canonicalAngle($angleKey)) {
            abort(response()->json([
                'message' => 'زاویه عکس انتخاب‌شده معتبر نیست.',
                'errors' => ['photo_angle_key' => ['یکی از هفت زاویه استاندارد را انتخاب کنید.']],
            ], 422));
        }

        $rawServices = $request->input('services', $currentMedia?->services ?: []);
        if (is_string($rawServices)) {
            $rawServices = json_decode($rawServices, true) ?: [];
        }
        if (! is_array($rawServices) || count($this->normalizeServiceTags($rawServices)) === 0) {
            abort(response()->json([
                'message' => 'انتخاب حداقل یک تگ خدمات برای عکس‌های قبل و بعد الزامی است.',
                'errors' => ['services' => ['حداقل یک خدمت را انتخاب کنید.']],
            ], 422));
        }

        foreach ($files as $file) {
            if (! Str::startsWith((string) $file?->getMimeType(), 'image/')) {
                abort(response()->json([
                    'message' => 'در پوشه عکس قبل یا بعد فقط فایل تصویری مجاز است.',
                ], 422));
            }
        }

        $duplicate = PatientMedia::query()
            ->where('patient_id', $folder->patient_id)
            ->where('folder_id', $folder->id)
            ->where('photo_angle_key', $angleKey)
            ->when($currentMedia, fn ($query) => $query->whereKeyNot($currentMedia->id))
            ->exists();

        if ($duplicate) {
            abort(response()->json([
                'message' => 'برای این زاویه قبلاً عکس ثبت شده است؛ همان عکس را جایگزین کنید.',
            ], 422));
        }
    }

    private function normalizeServiceTags(array $services): array
    {
        $available = collect($this->serviceGroups())
            ->flatMap(fn (array $group) => $group['items'])
            ->keyBy('key');

        return collect($services)
            ->map(function ($service) use ($available) {
                $key = is_array($service) ? ($service['key'] ?? $service['id'] ?? null) : $service;
                if ($key && $available->has((string) $key)) {
                    return $available->get((string) $key);
                }

                $name = trim((string) (is_array($service) ? ($service['name'] ?? '') : ''));
                return $name
                    ? $available->first(fn (array $tag) => $tag['name'] === $name)
                    : null;
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();
    }

    private function normalizeJalaliDate(?string $date): string
    {
        return strtr(str_replace('/', '-', trim((string) $date)), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function addConsentWarnings(Patient $patient, $folders)
    {
        $parentIds = PatientMediaFolder::query()
            ->where('patient_id', $patient->id)
            ->pluck('parent_id', 'id');

        $warningIds = [];
        $mediaFolderIds = PatientMedia::query()
            ->where('patient_id', $patient->id)
            ->where('usage_consent', false)
            ->whereNotNull('folder_id')
            ->pluck('folder_id')
            ->unique();

        foreach ($mediaFolderIds as $folderId) {
            $currentId = (int) $folderId;
            while ($currentId && ! isset($warningIds[$currentId])) {
                $warningIds[$currentId] = true;
                $currentId = (int) ($parentIds[$currentId] ?? 0);
            }
        }

        return $folders->each(function (PatientMediaFolder $folder) use ($warningIds) {
            $folder->setAttribute('has_no_usage_consent', isset($warningIds[$folder->id]));
        });
    }
}
