<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

class ActivityLogger
{
    public static function created(Model $model, array $metadata = []): void
    {
        self::write('created', $model, null, self::snapshot($model), $metadata);
    }

    public static function updated(Model $model, array $metadata = []): void
    {
        $changes = collect($model->getChanges())
            ->except(['updated_at'])
            ->all();

        if ($changes === []) {
            return;
        }

        $old = collect($changes)
            ->mapWithKeys(fn ($value, string $key) => [$key => $model->getOriginal($key)])
            ->all();

        self::write('updated', $model, $old, $changes, $metadata);
    }

    public static function deleted(Model $model, array $metadata = []): void
    {
        self::write('deleted', $model, self::snapshot($model), null, $metadata);
    }

    public static function manual(string $event, string $section, ?Model $subject = null, array $old = [], array $new = [], array $metadata = []): void
    {
        self::write($event, $subject, $old ?: null, $new ?: null, ['section' => $section, ...$metadata]);
    }

    private static function write(string $event, ?Model $subject, ?array $old, ?array $new, array $metadata = []): void
    {
        try {
            $user = Auth::user();
            $section = $metadata['section'] ?? ($subject
                ? (method_exists($subject, 'activitySection') ? $subject->activitySection() : class_basename($subject))
                : 'عمومی');
            unset($metadata['section']);

            ActivityLog::query()->create([
                'event' => $event,
                'section' => (string) $section,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject && method_exists($subject, 'activityLabel') ? $subject->activityLabel() : null,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'old_values' => self::clean($old),
                'new_values' => self::clean($new),
                'metadata' => self::clean($metadata) ?: null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 1000),
            ]);
        } catch (Throwable) {
            // Audit logging must never break the user's main action.
        }
    }

    private static function snapshot(Model $model): array
    {
        return $model->attributesToArray();
    }

    private static function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (['password', 'remember_token'] as $hidden) {
            if (array_key_exists($hidden, $values)) {
                $values[$hidden] = '[hidden]';
            }
        }

        return $values;
    }
}
