<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffTask;
use App\Models\StaffTaskAttachment;
use App\Models\StaffTaskSetting;
use App\Models\StaffTaskStatus;
use App\Models\StaffTaskVoice;
use App\Services\PatientImageOptimizer;
use App\Services\PatientPhotoSettings;
use App\Services\StaffTaskService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffTaskController extends Controller
{
    public function __construct(
        private readonly StaffTaskService $tasks,
        private readonly PatientImageOptimizer $imageOptimizer,
        private readonly PatientPhotoSettings $photoSettings,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $this->assertView($request);
        $this->tasks->runExpiry();

        $statuses = StaffTaskStatus::query()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (StaffTaskStatus $s) => $this->tasks->serializeStatus($s));
        $settings = StaffTaskSetting::current();
        $staff = Staff::query()->orderBy('name')->get()
            ->map(fn (Staff $s) => $this->tasks->serializeStaff($s));

        $user = $request->user();

        return response()->json([
            'statuses' => $statuses,
            'settings' => [
                'expiry_default_status_id' => $settings->expiry_default_status_id,
            ],
            'staff' => $staff,
            'can_manage' => $this->tasks->canManage($user),
            'can_admin' => $this->tasks->isClinicAdmin($user),
            'current_user_id' => $user?->id,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertView($request);
        $this->tasks->runExpiry();

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status_id' => ['nullable'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $query = StaffTask::query()->with(['status', 'expiryStatus', 'assignees', 'attachments.staff', 'voices.staff']);

        if (! empty($data['from'])) $query->whereDate('task_date', '>=', $data['from']);
        if (! empty($data['to'])) $query->whereDate('task_date', '<=', $data['to']);
        if (array_key_exists('status_id', $data) && $data['status_id'] !== null && $data['status_id'] !== '') {
            if ($data['status_id'] === 'none' || $data['status_id'] === 'null') {
                $query->whereNull('status_id');
            } else {
                $query->where('status_id', (int) $data['status_id']);
            }
        }
        if (! empty($data['staff_id'])) {
            $query->whereHas('assignees', fn ($q) => $q->where('staff.id', (int) $data['staff_id']));
        }

        if (! $this->tasks->canManage($request->user())) {
            $staff = $this->tasks->staffForUser($request->user());
            abort_unless($staff, 403);
            $query->where(function ($q) use ($staff, $request) {
                $q->whereHas('assignees', fn ($aq) => $aq->where('staff.id', $staff->id))
                    ->orWhere('created_by', $request->user()->id);
            });
        }

        $tasks = $query->orderBy('task_date')->orderBy('id')->get()
            ->map(fn (StaffTask $t) => $this->tasks->serialize($t));

        return response()->json(['tasks' => $tasks]);
    }

    public function show(Request $request, StaffTask $staffTask): JsonResponse
    {
        abort_unless($this->tasks->canAccessTask($request->user(), $staffTask), 403);
        return response()->json(['task' => $this->tasks->serialize($staffTask)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $this->validatedTask($request, true);

        $task = DB::transaction(function () use ($request, $data) {
            $task = StaffTask::query()->create([
                'title' => $data['title'],
                'note' => $data['note'] ?? null,
                'task_date' => $data['task_date'],
                'priority' => $data['priority'] ?? 'normal',
                'progress' => $data['progress'] ?? 0,
                'status_id' => null,
                'expiry_enabled' => (bool) ($data['expiry']['enabled'] ?? false),
                'expiry_date' => $data['expiry']['date'] ?? null,
                'expiry_time' => $data['expiry']['time'] ?? null,
                'expiry_status_id' => $data['expiry']['status_id'] ?? StaffTaskSetting::current()->expiry_default_status_id,
                'created_by' => $request->user()->id,
                'created_by_name' => $request->user()->name,
                'registered_at' => now(),
            ]);

            if ($request->hasFile('image')) {
                $task->update(['image_path' => $this->storeTaskImage($request->file('image'))]);
            }

            $this->tasks->syncAssignees($task, $data['assignee_ids'] ?? []);
            $this->storeUploadedAttachments($request, $task);
            $this->storeUploadedVoice($request, $task);

            return $task->fresh();
        });

        $task->load(['assignees']);
        $this->tasks->notifyAssigned($task, $task->assignees, 'task_assigned', 'وظیفه جدید به شما واگذار شد', $task->title);

        return response()->json(['task' => $this->tasks->serialize($task)], 201);
    }

    public function update(Request $request, StaffTask $staffTask): JsonResponse
    {
        $this->assertManage($request);
        $data = $this->validatedTask($request, false);
        $previousAssigneeIds = $staffTask->assignees()->pluck('staff.id');

        DB::transaction(function () use ($request, $staffTask, $data) {
            $staffTask->update([
                'title' => $data['title'] ?? $staffTask->title,
                'note' => array_key_exists('note', $data) ? $data['note'] : $staffTask->note,
                'task_date' => $data['task_date'] ?? $staffTask->task_date,
                'priority' => $data['priority'] ?? $staffTask->priority,
                'progress' => $data['progress'] ?? $staffTask->progress,
                'expiry_enabled' => array_key_exists('expiry', $data)
                    ? (bool) ($data['expiry']['enabled'] ?? false)
                    : $staffTask->expiry_enabled,
                'expiry_date' => array_key_exists('expiry', $data) ? ($data['expiry']['date'] ?? null) : $staffTask->expiry_date,
                'expiry_time' => array_key_exists('expiry', $data) ? ($data['expiry']['time'] ?? null) : $staffTask->expiry_time,
                'expiry_status_id' => array_key_exists('expiry', $data)
                    ? ($data['expiry']['status_id'] ?? null)
                    : $staffTask->expiry_status_id,
            ]);

            if ($request->boolean('remove_image')) {
                if ($staffTask->image_path) Storage::disk('public')->delete($staffTask->image_path);
                $staffTask->update(['image_path' => null]);
            }
            if ($request->hasFile('image')) {
                if ($staffTask->image_path) Storage::disk('public')->delete($staffTask->image_path);
                $staffTask->update(['image_path' => $this->storeTaskImage($request->file('image'))]);
            }

            if (array_key_exists('assignee_ids', $data)) {
                $this->tasks->syncAssignees($staffTask, $data['assignee_ids'] ?? []);
            }
            $this->storeUploadedAttachments($request, $staffTask);
            $this->storeUploadedVoice($request, $staffTask);
        });

        $staffTask->refresh()->load('assignees');
        $newAssignees = $staffTask->assignees->whereNotIn('id', $previousAssigneeIds);
        if ($newAssignees->isNotEmpty()) {
            $this->tasks->notifyAssigned($staffTask, $newAssignees, 'task_assigned', 'وظیفه جدید به شما واگذار شد', $staffTask->title);
        }

        return response()->json(['task' => $this->tasks->serialize($staffTask)]);
    }

    public function updateStatus(Request $request, StaffTask $staffTask): JsonResponse
    {
        abort_unless($this->tasks->canAccessTask($request->user(), $staffTask), 403);
        $data = $request->validate([
            'status_id' => ['nullable', 'integer', 'exists:staff_task_statuses,id'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $staffTask->update([
            'status_id' => $data['status_id'] ?? null,
            'auto_expired' => false,
            'progress' => $data['progress'] ?? $staffTask->progress,
        ]);
        $staffTask->refresh()->load(['status', 'assignees']);
        $this->tasks->syncPayrollImpact($staffTask);

        $label = $staffTask->status?->title ?? 'بدون وضعیت';
        $this->tasks->notifyAssigned(
            $staffTask,
            $staffTask->assignees,
            'task_status_changed',
            'وضعیت وظیفه تغییر کرد',
            $staffTask->title.' → '.$label
        );

        return response()->json(['task' => $this->tasks->serialize($staffTask)]);
    }

    public function destroy(Request $request, StaffTask $staffTask): JsonResponse
    {
        abort_unless($this->tasks->canDeleteTask($request->user(), $staffTask), 403, 'فقط ایجادکننده وظیفه یا مدیر کل می‌تواند آن را حذف کند.');
        $this->tasks->clearPayrollImpact($staffTask);
        if ($staffTask->image_path) Storage::disk('public')->delete($staffTask->image_path);
        foreach ($staffTask->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }
        foreach ($staffTask->voices as $voice) {
            Storage::disk('public')->delete($voice->path);
        }
        $staffTask->delete();
        return response()->json(['success' => true]);
    }

    public function addVoice(Request $request, StaffTask $staffTask): JsonResponse
    {
        abort_unless($this->tasks->canAccessTask($request->user(), $staffTask), 403);
        $request->validate([
            'voice' => ['required', 'file', 'max:20480'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $staff = $this->tasks->staffForUser($request->user());
        $voice = StaffTaskVoice::query()->create([
            'staff_task_id' => $staffTask->id,
            'user_id' => $request->user()->id,
            'staff_id' => $staff?->id,
            'uploader_name' => $request->user()->name,
            'path' => $request->file('voice')->store('staff-tasks/voices', 'public'),
            'duration' => $request->integer('duration') ?: null,
        ]);

        $serialized = $this->tasks->serialize($staffTask->fresh(), $request->user());
        $voicePayload = collect($serialized['voices'])->firstWhere('id', $voice->id) ?: [
            'id' => $voice->id,
            'url' => $voice->url,
            'duration' => $voice->duration,
            'user_id' => $voice->user_id,
            'uploader_name' => $voice->uploader_name,
            'uploader_avatar_url' => $staff?->avatar_url,
            'created_at' => optional($voice->created_at)?->toIso8601String(),
            'can_delete' => true,
        ];

        return response()->json(['voice' => $voicePayload, 'task' => $serialized], 201);
    }

    public function destroyAttachment(Request $request, StaffTask $staffTask, StaffTaskAttachment $attachment): JsonResponse
    {
        abort_unless((int) $attachment->staff_task_id === (int) $staffTask->id, 404);
        abort_unless(
            $this->tasks->canDeleteAttachment($request->user(), $staffTask, $attachment),
            403,
            'اجازه حذف این پیوست را ندارید.'
        );
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(['task' => $this->tasks->serialize($staffTask->fresh(), $request->user())]);
    }

    public function destroyVoice(Request $request, StaffTask $staffTask, StaffTaskVoice $voice): JsonResponse
    {
        abort_unless((int) $voice->staff_task_id === (int) $staffTask->id, 404);
        abort_unless(
            $this->tasks->canDeleteVoice($request->user(), $staffTask, $voice),
            403,
            'اجازه حذف این پیام صوتی را ندارید.'
        );
        Storage::disk('public')->delete($voice->path);
        $voice->delete();
        return response()->json(['task' => $this->tasks->serialize($staffTask->fresh(), $request->user())]);
    }

    public function destroyImage(Request $request, StaffTask $staffTask): JsonResponse
    {
        abort_unless(
            $this->tasks->canDeleteTaskMedia($request->user(), $staffTask),
            403,
            'اجازه حذف تصویر این وظیفه را ندارید.'
        );
        if ($staffTask->image_path) {
            Storage::disk('public')->delete($staffTask->image_path);
            $staffTask->update(['image_path' => null]);
        }
        return response()->json(['task' => $this->tasks->serialize($staffTask->fresh(), $request->user())]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $this->assertView($request);
        $statuses = StaffTaskStatus::query()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (StaffTaskStatus $s) => $this->tasks->serializeStatus($s));
        return response()->json([
            'statuses' => $statuses,
            'settings' => ['expiry_default_status_id' => StaffTaskSetting::current()->expiry_default_status_id],
        ]);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'integer', 'min:0', 'max:7'],
            'effect' => ['nullable', Rule::in(['plus', 'minus', 'none'])],
            'amount' => ['nullable', 'integer', 'min:0'],
        ]);
        $max = (int) StaffTaskStatus::query()->max('sort_order');
        $status = StaffTaskStatus::query()->create([
            'title' => $data['title'],
            'color' => $data['color'] ?? ($max % 8),
            'effect' => $data['effect'] ?? 'none',
            'amount' => $data['amount'] ?? 0,
            'sort_order' => $max + 1,
            'is_system' => false,
        ]);
        return response()->json(['status' => $this->tasks->serializeStatus($status)], 201);
    }

    public function updateStatusDefinition(Request $request, StaffTaskStatus $status): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'integer', 'min:0', 'max:7'],
            'effect' => ['sometimes', Rule::in(['plus', 'minus', 'none'])],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $status->update($data);

        // Re-sync payroll for tasks currently on this status.
        StaffTask::query()->where('status_id', $status->id)->with('assignees')->each(function (StaffTask $task) {
            $this->tasks->syncPayrollImpact($task);
        });

        return response()->json(['status' => $this->tasks->serializeStatus($status->fresh())]);
    }

    public function destroyStatus(Request $request, StaffTaskStatus $status): JsonResponse
    {
        $this->assertManage($request);
        $settings = StaffTaskSetting::current();
        if ((int) $settings->expiry_default_status_id === (int) $status->id) {
            $settings->update(['expiry_default_status_id' => null]);
        }
        StaffTask::query()->where('status_id', $status->id)->update(['status_id' => null]);
        StaffTask::query()->where('expiry_status_id', $status->id)->update(['expiry_status_id' => null]);
        $status->delete();
        return response()->json(['success' => true]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'expiry_default_status_id' => ['nullable', 'integer', 'exists:staff_task_statuses,id'],
        ]);
        $settings = StaffTaskSetting::current();
        // Allow clearing by sending null / missing with clear flag
        if ($request->has('expiry_default_status_id')) {
            $settings->update(['expiry_default_status_id' => $data['expiry_default_status_id'] ?? null]);
        }
        return response()->json([
            'settings' => ['expiry_default_status_id' => $settings->fresh()->expiry_default_status_id],
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->assertView($request);
        $this->tasks->runExpiry();
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'date' => ['nullable', 'date'],
        ]);
        $anchor = ! empty($data['date']) ? Carbon::parse($data['date'], 'Asia/Tehran') : now('Asia/Tehran');
        return response()->json($this->tasks->report($data['period'] ?? 'monthly', $anchor, $request->user()));
    }

    public function runExpiry(Request $request): JsonResponse
    {
        $this->assertManage($request);
        return response()->json([
            'expired' => $this->tasks->runExpiry(),
            'approaching' => $this->tasks->notifyApproachingDeadlines(),
        ]);
    }

    private function validatedTask(Request $request, bool $creating): array
    {
        $assigneeIds = $request->input('assignee_ids');
        if (is_string($assigneeIds)) {
            $decoded = json_decode($assigneeIds, true);
            $request->merge(['assignee_ids' => is_array($decoded) ? $decoded : array_filter(explode(',', $assigneeIds))]);
        }
        $expiry = $request->input('expiry');
        if (is_string($expiry)) {
            $decoded = json_decode($expiry, true);
            if (is_array($decoded)) $request->merge(['expiry' => $decoded]);
        }

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'task_date' => [$creating ? 'required' : 'sometimes', 'date'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'assignee_ids' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'exists:staff,id'],
            'expiry' => ['nullable', 'array'],
            'expiry.enabled' => ['nullable', 'boolean'],
            'expiry.date' => ['nullable', 'date'],
            'expiry.time' => ['nullable', 'date_format:H:i'],
            'expiry.status_id' => ['nullable', 'integer', 'exists:staff_task_statuses,id'],
            // Use file+mimes (not Laravel "image") so HEIC/HEIF from Apple Photos pass validation.
            'image' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,heic,heif'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
            'voice' => ['nullable', 'file', 'max:20480'],
            'voice_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'image.uploaded' => 'آپلود تصویر انجام نشد. اگر عکس HEIC است، معمولاً به‌خاطر سقف حجم PHP است.',
            'image.file' => 'فایل تصویر معتبر نیست.',
            'image.max' => 'حجم تصویر بیشتر از ۵۰ مگابایت است.',
            'image.mimes' => 'فرمت تصویر مجاز نیست. از JPG، PNG، WEBP، GIF یا HEIC استفاده کنید.',
        ]);
    }

    /** Same HEIC→WebP + compression pipeline as clinic patient media / تنظیمات photo settings. */
    private function storeTaskImage(UploadedFile $file): string
    {
        try {
            $stored = $this->imageOptimizer->store(
                $file,
                'staff-tasks/images',
                $this->photoSettings->current()
            );
        } catch (ValidationException $e) {
            $messages = $e->errors();
            if (isset($messages['file'])) {
                throw ValidationException::withMessages(['image' => $messages['file']]);
            }
            throw $e;
        }

        return $stored['path'];
    }

    private function isImageUpload(UploadedFile $file): bool
    {
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|heic|heif)$/i', (string) $file->getClientOriginalName());
    }

    private function storeUploadedAttachments(Request $request, StaffTask $task): void
    {
        if (! $request->hasFile('attachments')) return;
        $staff = $this->tasks->staffForUser($request->user());
        foreach ($request->file('attachments') as $file) {
            $isImage = $this->isImageUpload($file);
            if ($isImage) {
                try {
                    $stored = $this->imageOptimizer->store(
                        $file,
                        'staff-tasks/files',
                        $this->photoSettings->current()
                    );
                    $path = $stored['path'];
                    $mime = $stored['mime_type'];
                    $size = $stored['size'];
                } catch (ValidationException $e) {
                    $messages = $e->errors();
                    if (isset($messages['file'])) {
                        throw ValidationException::withMessages(['attachments' => $messages['file']]);
                    }
                    throw $e;
                }
            } else {
                $path = $file->store('staff-tasks/files', 'public');
                $mime = (string) $file->getMimeType();
                $size = $file->getSize();
            }

            StaffTaskAttachment::query()->create([
                'staff_task_id' => $task->id,
                'user_id' => $request->user()->id,
                'staff_id' => $staff?->id,
                'uploader_name' => $request->user()->name,
                'kind' => $isImage ? 'image' : 'file',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $size,
            ]);
        }
    }

    private function storeUploadedVoice(Request $request, StaffTask $task): void
    {
        if (! $request->hasFile('voice')) return;
        $staff = $this->tasks->staffForUser($request->user());
        StaffTaskVoice::query()->create([
            'staff_task_id' => $task->id,
            'user_id' => $request->user()->id,
            'staff_id' => $staff?->id,
            'uploader_name' => $request->user()->name,
            'path' => $request->file('voice')->store('staff-tasks/voices', 'public'),
            'duration' => $request->integer('voice_duration') ?: null,
        ]);
    }

    private function assertView(Request $request): void
    {
        abort_unless(
            $request->user()->can('staff_tasks.view')
            || $request->user()->can('staff_tasks.manage')
            || $this->tasks->canManage($request->user()),
            403
        );
    }

    private function assertManage(Request $request): void
    {
        abort_unless($this->tasks->canManage($request->user()), 403);
    }
}
