<?php

namespace App\Services;

use App\Models\ResourceAdjustment;
use App\Models\Staff;
use App\Models\StaffTask;
use App\Models\StaffTaskSetting;
use App\Models\StaffTaskStatus;
use App\Models\User;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StaffTaskService
{
    private JalaliMonthWindow $jalali;

    public function __construct(private readonly EmployeePortalService $portal)
    {
        $this->jalali = new JalaliMonthWindow();
    }

    public function serialize(StaffTask $task, ?User $viewer = null): array
    {
        $task->loadMissing(['status', 'expiryStatus', 'assignees', 'attachments.staff', 'voices.staff']);
        $viewer = $viewer ?? auth()->user();
        $canDeleteTask = $viewer ? $this->canDeleteTask($viewer, $task) : false;
        $canDeleteMedia = $viewer ? $this->canDeleteTaskMedia($viewer, $task) : false;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'note' => $task->note,
            'task_date' => optional($task->task_date)->toDateString(),
            'priority' => $task->priority,
            'progress' => (int) $task->progress,
            'status_id' => $task->status_id,
            'status' => $task->status ? $this->serializeStatus($task->status) : null,
            'auto_expired' => (bool) $task->auto_expired,
            'expiry' => [
                'enabled' => (bool) $task->expiry_enabled,
                'date' => optional($task->expiry_date)->toDateString(),
                'time' => $task->expiry_time ? substr((string) $task->expiry_time, 0, 5) : null,
                'status_id' => $task->expiry_status_id,
            ],
            'image_url' => $task->image_url,
            'created_by' => $task->created_by,
            'created_by_name' => $task->created_by_name,
            'registered_at' => optional($task->registered_at ?? $task->created_at)?->toIso8601String(),
            'assignee_ids' => $task->assignees->pluck('id')->values(),
            'assignees' => $task->assignees->map(fn (Staff $s) => $this->serializeStaff($s))->values(),
            'attachments' => $task->attachments->map(fn ($a) => [
                'id' => $a->id,
                'kind' => $a->kind,
                'url' => $a->url,
                'original_name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'user_id' => $a->user_id,
                'uploader_name' => $a->uploader_name,
                'uploader_avatar_url' => $a->uploader_avatar_url,
                'created_at' => optional($a->created_at)?->toIso8601String(),
                'can_delete' => $viewer && $this->canDeleteAttachment($viewer, $task, $a),
            ])->values(),
            'voices' => $task->voices->map(fn ($v) => [
                'id' => $v->id,
                'url' => $v->url,
                'duration' => $v->duration,
                'user_id' => $v->user_id,
                'uploader_name' => $v->uploader_name,
                'uploader_avatar_url' => $v->uploader_avatar_url,
                'created_at' => optional($v->created_at)?->toIso8601String(),
                'can_delete' => $viewer && $this->canDeleteVoice($viewer, $task, $v),
            ])->values(),
            'can_delete' => $canDeleteTask,
            'can_delete_media' => $canDeleteMedia,
            'is_overdue' => $this->isOverdue($task),
            'payroll_effect' => $task->status?->signedAmount() ?? 0,
        ];
    }

    public function serializeStatus(StaffTaskStatus $status): array
    {
        return [
            'id' => $status->id,
            'title' => $status->title,
            'color' => (int) $status->color,
            'effect' => $status->effect,
            'amount' => (int) $status->amount,
            'sort_order' => (int) $status->sort_order,
            'is_system' => (bool) $status->is_system,
        ];
    }

    public function serializeStaff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'avatar_url' => $staff->avatar_url,
            'user_id' => $staff->user_id,
        ];
    }

    public function staffForUser(?User $user): ?Staff
    {
        if (! $user) return null;
        return Staff::query()->where('user_id', $user->id)->first();
    }

    public function canManage(User $user): bool
    {
        return $user->can('staff_tasks.manage')
            || collect($user->getRoleNames())->intersect(['مدیر کل', 'مدیر سیستم', 'مدیر مجموعه', 'super admin', 'super-admin'])->isNotEmpty();
    }

    /** مدیر کل / super-admin — can delete any task or media. */
    public function isClinicAdmin(User $user): bool
    {
        return collect($user->getRoleNames())
            ->intersect(['مدیر کل', 'مدیر سیستم', 'super admin', 'super-admin'])
            ->isNotEmpty();
    }

    public function canDeleteTask(User $user, StaffTask $task): bool
    {
        if ($this->isClinicAdmin($user)) {
            return true;
        }

        return (int) $task->created_by === (int) $user->id;
    }

    /** Task creator or clinic admin may remove any media on the task. */
    public function canDeleteTaskMedia(User $user, StaffTask $task): bool
    {
        return $this->canDeleteTask($user, $task);
    }

    public function canDeleteAttachment(User $user, StaffTask $task, $attachment): bool
    {
        if ($this->canDeleteTaskMedia($user, $task)) {
            return true;
        }

        return (int) ($attachment->user_id ?? 0) === (int) $user->id;
    }

    public function canDeleteVoice(User $user, StaffTask $task, $voice): bool
    {
        if ($this->canDeleteTaskMedia($user, $task)) {
            return true;
        }

        return (int) ($voice->user_id ?? 0) === (int) $user->id;
    }

    public function canAccessTask(User $user, StaffTask $task): bool
    {
        if ($this->canManage($user)) return true;
        $staff = $this->staffForUser($user);
        if (! $staff) return false;
        return $task->assignees()->where('staff.id', $staff->id)->exists()
            || (int) $task->created_by === (int) $user->id;
    }

    public function syncAssignees(StaffTask $task, array $staffIds): void
    {
        $ids = collect($staffIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $task->assignees()->sync($ids);
    }

    public function notifyAssigned(StaffTask $task, Collection $assignees, string $type, string $title, ?string $body = null): void
    {
        foreach ($assignees as $staff) {
            if (! $staff->user_id) continue;
            $this->portal->notifyEmployee(
                'staff',
                (int) $staff->id,
                $type,
                $title,
                $body ?? $task->title,
                'staff_tasks',
                ['task_id' => $task->id]
            );
        }
    }

    public function syncPayrollImpact(StaffTask $task): void
    {
        if (! Schema::hasTable('resource_adjustments')) return;

        ResourceAdjustment::query()
            ->where('source_task_id', $task->id)
            ->where('status', 'active')
            ->update(['status' => 'deleted']);

        $task->loadMissing(['status', 'assignees']);
        $amount = $task->status?->signedAmount() ?? 0;
        if ($amount === 0 || $task->assignees->isEmpty()) return;

        $jalaliDate = $this->jalali->date(Carbon::parse($task->task_date));
        $month = substr($jalaliDate, 0, 7);
        $kind = $amount > 0 ? 'bonus' : 'deduction';
        $title = 'تأثیر وظیفه: '.$task->title;

        foreach ($task->assignees as $staff) {
            ResourceAdjustment::query()->create([
                'month' => $month,
                'resource_type' => 'staff',
                'resource_id' => $staff->id,
                'source_task_id' => $task->id,
                'amount' => $amount,
                'reason' => $title,
                'kind' => $kind,
                'occurred_on' => $jalaliDate,
                'title' => $title,
                'description' => $task->status?->title,
                'status' => 'active',
                'created_by' => $task->created_by,
                'created_by_name' => $task->created_by_name ?: 'سیستم وظایف',
            ]);
        }
    }

    public function clearPayrollImpact(StaffTask $task): void
    {
        if (! Schema::hasTable('resource_adjustments')) return;
        ResourceAdjustment::query()
            ->where('source_task_id', $task->id)
            ->where('status', 'active')
            ->update(['status' => 'deleted']);
    }

    public function runExpiry(?Carbon $now = null): int
    {
        $now = $now ?? now('Asia/Tehran');
        $settings = StaffTaskSetting::current();
        $count = 0;

        StaffTask::query()
            ->whereNull('status_id')
            ->where('expiry_enabled', true)
            ->whereNotNull('expiry_date')
            ->with(['assignees', 'expiryStatus'])
            ->chunkById(100, function ($tasks) use ($now, $settings, &$count) {
                foreach ($tasks as $task) {
                    $statusId = $task->expiry_status_id ?: $settings->expiry_default_status_id;
                    if (! $statusId || ! StaffTaskStatus::query()->whereKey($statusId)->exists()) continue;
                    $time = $task->expiry_time ? substr((string) $task->expiry_time, 0, 5) : '23:59';
                    $deadline = Carbon::parse($task->expiry_date->toDateString().' '.$time, 'Asia/Tehran');
                    if ($deadline->gt($now)) continue;

                    $task->update([
                        'status_id' => $statusId,
                        'auto_expired' => true,
                    ]);
                    $task->refresh()->load(['status', 'assignees']);
                    $this->syncPayrollImpact($task);
                    $this->notifyAssigned($task, $task->assignees, 'task_overdue', 'وظیفه عقب‌افتاده', $task->title.' → '.($task->status?->title ?? ''));
                    $count++;
                }
            });

        return $count;
    }

    public function notifyApproachingDeadlines(?Carbon $now = null): int
    {
        $now = $now ?? now('Asia/Tehran');
        $soon = $now->copy()->addDay();
        $count = 0;

        StaffTask::query()
            ->whereNull('status_id')
            ->where('expiry_enabled', true)
            ->whereNotNull('expiry_date')
            ->with('assignees')
            ->chunkById(100, function ($tasks) use ($now, $soon, &$count) {
                foreach ($tasks as $task) {
                    $time = $task->expiry_time ? substr((string) $task->expiry_time, 0, 5) : '23:59';
                    $deadline = Carbon::parse($task->expiry_date->toDateString().' '.$time, 'Asia/Tehran');
                    if ($deadline->lte($now) || $deadline->gt($soon)) continue;
                    $this->notifyAssigned($task, $task->assignees, 'task_deadline_approaching', 'مهلت وظیفه نزدیک است', $task->title);
                    $count++;
                }
            });

        return $count;
    }

    public function isOverdue(StaffTask $task): bool
    {
        if ($task->status_id || ! $task->expiry_enabled || ! $task->expiry_date) return false;
        $time = $task->expiry_time ? substr((string) $task->expiry_time, 0, 5) : '23:59';
        $deadline = Carbon::parse($task->expiry_date->toDateString().' '.$time, 'Asia/Tehran');
        return $deadline->lte(now('Asia/Tehran'));
    }

    public function report(string $period, ?Carbon $anchor = null, ?User $user = null): array
    {
        $anchor = $anchor ?? now('Asia/Tehran');
        [$from, $to] = $this->periodRange($period, $anchor);

        $query = StaffTask::query()
            ->with(['status', 'assignees', 'voices', 'attachments'])
            ->whereBetween('task_date', [$from->toDateString(), $to->toDateString()]);

        $tasks = $query->orderByDesc('task_date')->orderByDesc('id')->get();
        $staffId = $user ? $this->staffForUser($user)?->id : null;

        $serialized = $tasks->map(fn (StaffTask $t) => $this->serialize($t));

        $doneIds = StaffTaskStatus::query()->where(function ($q) {
            $q->where('effect', 'plus')->orWhere('title', 'like', '%انجام شد%');
        })->pluck('id');
        $notDoneIds = StaffTaskStatus::query()->where(function ($q) {
            $q->where('effect', 'minus')->orWhere('title', 'like', '%انجام نشد%')->orWhere('title', 'like', '%تکمیل نشد%');
        })->pluck('id');

        // «واگذار شده به من» = assignee; «تسک‌های من» = created by me
        $assignedToMe = $tasks->filter(fn (StaffTask $t) => $staffId && $t->assignees->contains('id', $staffId));
        $createdByMe = $tasks->filter(fn (StaffTask $t) => $user && (int) $t->created_by === (int) $user->id);
        $mine = $assignedToMe; // backward-compat alias
        $assignedByMe = $createdByMe; // backward-compat alias
        $inProgress = $tasks->filter(fn (StaffTask $t) => $t->status_id && ! $doneIds->contains($t->status_id) && ! $notDoneIds->contains($t->status_id));
        $done = $tasks->filter(fn (StaffTask $t) => $doneIds->contains($t->status_id));
        $notDone = $tasks->filter(fn (StaffTask $t) => $notDoneIds->contains($t->status_id));
        $overdue = $tasks->filter(fn (StaffTask $t) => $this->isOverdue($t));

        $statusChart = StaffTaskStatus::query()->orderBy('sort_order')->get()->map(function (StaffTaskStatus $st) use ($tasks) {
            return [
                'status_id' => $st->id,
                'title' => $st->title,
                'color' => (int) $st->color,
                'count' => $tasks->where('status_id', $st->id)->count(),
            ];
        })->values();

        $pendingCount = $tasks->whereNull('status_id')->count();
        $statusChart->push(['status_id' => null, 'title' => 'بدون وضعیت', 'color' => 7, 'count' => $pendingCount]);

        $perStaff = Staff::query()->orderBy('name')->get()->map(function (Staff $staff) use ($tasks, $doneIds, $notDoneIds) {
            $assigned = $tasks->filter(fn (StaffTask $t) => $t->assignees->contains('id', $staff->id));
            $doneCount = $assigned->filter(fn (StaffTask $t) => $doneIds->contains($t->status_id))->count();
            $notDoneCount = $assigned->filter(fn (StaffTask $t) => $notDoneIds->contains($t->status_id))->count();
            $overdueCount = $assigned->filter(fn (StaffTask $t) => $this->isOverdue($t))->count();
            $total = $assigned->count();
            return [
                'staff' => $this->serializeStaff($staff),
                'total' => $total,
                'done' => $doneCount,
                'not_done' => $notDoneCount,
                'overdue' => $overdueCount,
                'percent_done' => $total ? round(($doneCount / $total) * 100) : 0,
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        $best = $perStaff->sortByDesc(fn ($row) => [$row['percent_done'], $row['done'], -$row['overdue']])->first();

        $totalAssigned = max(1, $tasks->count());
        $percentDone = round(($done->count() / $totalAssigned) * 100);

        return [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'all' => $tasks->count(),
                'mine' => $mine->count(),
                'assigned_to_me' => $assignedToMe->count(),
                'created_by_me' => $createdByMe->count(),
                'assigned_by_me' => $assignedByMe->count(),
                'in_progress' => $inProgress->count(),
                'done' => $done->count(),
                'not_done' => $notDone->count(),
                'overdue' => $overdue->count(),
                'percent_done' => $percentDone,
            ],
            'tasks' => $serialized,
            'status_chart' => $statusChart,
            'donut_chart' => [
                ['key' => 'done', 'title' => 'انجام شد', 'count' => $done->count(), 'color' => '#22c55e'],
                ['key' => 'in_progress', 'title' => 'در حال انجام', 'count' => $inProgress->count(), 'color' => '#3b82f6'],
                ['key' => 'not_done', 'title' => 'انجام نشده', 'count' => $notDone->count(), 'color' => '#94a3b8'],
                ['key' => 'overdue', 'title' => 'عقب افتاده', 'count' => $overdue->count(), 'color' => '#ef4444'],
            ],
            'overdue_tasks' => $overdue->map(fn (StaffTask $t) => $this->serialize($t))->values(),
            'per_staff' => $perStaff,
            'best_performer' => $best,
            'counts' => [
                'done' => $done->count(),
                'not_done' => $notDone->count(),
                'overdue' => $overdue->count(),
                'in_progress' => $inProgress->count(),
            ],
            'task_ids' => [
                'all' => $tasks->pluck('id')->values(),
                'created_by_me' => $createdByMe->pluck('id')->values(),
                'assigned_to_me' => $assignedToMe->pluck('id')->values(),
                'overdue' => $overdue->pluck('id')->values(),
            ],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(string $period, Carbon $anchor): array
    {
        $jalali = $this->jalali->date($anchor);
        [$jy, $jm, $jd] = array_map('intval', explode('-', $jalali));

        return match ($period) {
            'daily' => [
                Carbon::parse($anchor->toDateString())->startOfDay(),
                Carbon::parse($anchor->toDateString())->endOfDay(),
            ],
            'weekly' => [
                Carbon::parse($anchor->toDateString())->startOfWeek(Carbon::SATURDAY),
                Carbon::parse($anchor->toDateString())->endOfWeek(Carbon::FRIDAY),
            ],
            'yearly' => [
                Carbon::parse($this->jalali->toGregorian(sprintf('%04d-01-01', $jy)))->startOfDay(),
                Carbon::parse($this->jalali->toGregorian(sprintf('%04d-12-%02d', $jy, $this->daysInJalaliMonth($jy, 12))))->endOfDay(),
            ],
            default => [ // monthly
                Carbon::parse($this->jalali->toGregorian(sprintf('%04d-%02d-01', $jy, $jm)))->startOfDay(),
                Carbon::parse($this->jalali->toGregorian(sprintf('%04d-%02d-%02d', $jy, $jm, $this->daysInJalaliMonth($jy, $jm))))->endOfDay(),
            ],
        };
    }

    private function daysInJalaliMonth(int $year, int $month): int
    {
        if ($month <= 6) return 31;
        if ($month <= 11) return 30;
        return in_array(($year + 12) % 33, [1, 5, 9, 13, 17, 22, 26, 30], true) ? 30 : 29;
    }
}
