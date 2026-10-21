<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantStorageService
{
    public const GIB = 1073741824;
    public const DEFAULT_BASE_QUOTA_GB = 2;
    public const DEFAULT_BASE_QUOTA_BYTES = self::DEFAULT_BASE_QUOTA_GB * self::GIB;

    private function central()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }

    public function ensureRow(string $tenantId): void
    {
        $this->central()->table('central_tenant_storage')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'base_quota_bytes' => self::DEFAULT_BASE_QUOTA_BYTES,
            'used_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function quotaBytes(string $tenantId, ?object $row = null): int
    {
        $row ??= $this->central()->table('central_tenant_storage')->where('tenant_id', $tenantId)->first();
        $extra = $this->central()->table('central_storage_orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->sum('size_bytes');

        return (int) ($row->base_quota_bytes ?? self::DEFAULT_BASE_QUOTA_BYTES) + (int) $extra;
    }

    public function summary(string $tenantId): array
    {
        $this->ensureRow($tenantId);
        $row = $this->central()->table('central_tenant_storage')->where('tenant_id', $tenantId)->first();
        $quota = $this->quotaBytes($tenantId, $row);
        $used = (int) $row->used_bytes;

        return [
            'base_quota_bytes' => (int) $row->base_quota_bytes,
            'quota_bytes' => $quota,
            'used_bytes' => $used,
            'remaining_bytes' => max(0, $quota - $used),
            'percentage' => $quota > 0 ? round($used * 100 / $quota, 1) : 100,
            'breakdown' => json_decode($row->breakdown ?: '{}', true) ?: [],
            'scanned_at' => $row->scanned_at,
        ];
    }

    /** Scan file metadata on the tenant's public disk; file contents are never read. */
    public function reconcileCurrentTenant(): array
    {
        $tenantId = (string) tenant('id');
        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for storage reconciliation.');
        }

        $this->ensureRow($tenantId);
        return $this->central()->transaction(function () use ($tenantId) {
            $row = $this->central()->table('central_tenant_storage')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (! $row->scanned_at) {
                $this->importLegacyCurrentTenant();
            }
            $disk = Storage::disk('public');
            $breakdown = ['patient_photos' => 0, 'patient_videos' => 0, 'patient_profiles' => 0, 'staff_and_users' => 0, 'audio_and_notes' => 0, 'other' => 0];
            $total = 0;
            foreach ($disk->allFiles() as $path) {
                $size = $disk->size($path);
                if ($size === false) {
                    continue;
                }
                $size = (int) $size;
                $total += $size;
                $category = match (true) {
                    str_contains($path, '/media/') && str_starts_with($path, 'patients/') && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'mov', 'avi', 'webm'], true) => 'patient_videos',
                    str_contains($path, '/media/') && str_starts_with($path, 'patients/') => 'patient_photos',
                    str_starts_with($path, 'patients/') => 'patient_profiles',
                    str_starts_with($path, 'users/'), str_starts_with($path, 'doctors/'), str_starts_with($path, 'staff/') => 'staff_and_users',
                    str_starts_with($path, 'appointment-notes/'), str_starts_with($path, 'beauty-annotations/') => 'audio_and_notes',
                    default => 'other',
                };
                $breakdown[$category] += $size;
            }

            $this->central()->table('central_tenant_storage')->where('tenant_id', $tenantId)->update([
                'used_bytes' => $total,
                'breakdown' => json_encode($breakdown),
                'scanned_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->summary($tenantId);
        });
    }

    /** Copy referenced legacy files into the isolated tenant directory, without removing originals. */
    public function importLegacyCurrentTenant(): void
    {
        $tenantId = (string) tenant('id');
        $legacyRoot = realpath((string) config('filesystems.legacy_public_root'));
        if (! $legacyRoot) {
            return;
        }

        $paths = [];
        $columns = [
            'patient_media' => ['path'],
            'patients' => ['profile_photo_path', 'profile_thumbnail_path'],
            'users' => ['profile_photo_path', 'profile_thumbnail_path'],
            'doctors' => ['profile_photo_path', 'profile_thumbnail_path'],
            'staff' => ['profile_photo_path', 'profile_thumbnail_path'],
            'channels' => ['icon_image_path'],
            'appointment_note_messages' => ['audio_path', 'image_path'],
            'beauty_annotations' => ['voice_path'],
        ];
        foreach ($columns as $table => $fields) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                continue;
            }
            foreach ($fields as $field) {
                if (Schema::connection('tenant')->hasColumn($table, $field)) {
                    foreach (DB::connection('tenant')->table($table)->whereNotNull($field)->where($field, '<>', '')->distinct()->pluck($field) as $path) {
                        $paths[] = $path;
                    }
                }
            }
        }
        if (Schema::connection('tenant')->hasTable('app_settings')) {
            $logo = DB::connection('tenant')->table('app_settings')->where('key', 'company_logo_path')->value('value');
            if ($logo) {
                $paths[] = $logo;
            }
        }
        foreach ($this->central()->table('central_service_tickets')->where('tenant_id', $tenantId)
            ->whereNotNull('attachment_path')->pluck('attachment_path') as $path) {
            $paths[] = $path;
        }

        $disk = Storage::disk('public');
        foreach (array_unique($paths) as $path) {
            $path = (string) $path;
            if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || $disk->exists($path)) {
                continue;
            }
            $source = realpath($legacyRoot.'/'.$path);
            if (! $source || ! str_starts_with($source, $legacyRoot.DIRECTORY_SEPARATOR) || ! is_file($source)) {
                continue;
            }
            $stream = fopen($source, 'rb');
            try {
                if (! $disk->put($path, $stream)) {
                    throw new RuntimeException('Could not import legacy file: '.$path);
                }
            } finally {
                fclose($stream);
            }
        }

        if (Schema::connection('tenant')->hasTable('app_settings')) {
            $logo = DB::connection('tenant')->table('app_settings')->where('key', 'company_logo_path')->value('value');
            if ($logo && $disk->exists($logo)) {
                DB::connection('tenant')->table('app_settings')->where('key', 'company_logo')
                    ->update(['value' => $disk->url($logo)]);
            }
        }
    }

    public function reconcileTenant(Tenant $tenant): array
    {
        $previous = tenant();
        tenancy()->initialize($tenant);
        try {
            return $this->reconcileCurrentTenant();
        } finally {
            if ($previous && $previous->getTenantKey() !== $tenant->getTenantKey()) {
                tenancy()->initialize($previous);
            } elseif (! $previous) {
                tenancy()->end();
            }
        }
    }

    public function incomingBytes(array $files): int
    {
        $sum = 0;
        array_walk_recursive($files, function ($file) use (&$sum) {
            if ($file instanceof UploadedFile) {
                $sum += (int) $file->getSize();
            }
        });
        return $sum;
    }

    public function replaceableBytes(Request $request): int
    {
        $paths = [];
        $tableAndId = null;
        $route = $request->route();
        $uri = $request->path();

        if ($request->hasFile('file') && $route?->parameter('media')) {
            $tableAndId = ['patient_media', $route->parameter('media'), ['path']];
        } elseif ($request->hasFile('photo') && str_contains($uri, '/patients/') && str_ends_with($uri, '/profile-photo')) {
            $tableAndId = ['patients', $route?->parameter('patient'), ['profile_photo_path', 'profile_thumbnail_path']];
        } elseif ($request->hasFile('photo') && str_contains($uri, '/doctors/')) {
            $tableAndId = ['doctors', $route?->parameter('doctor'), ['profile_photo_path', 'profile_thumbnail_path']];
        } elseif ($request->hasFile('photo') && str_contains($uri, '/staff/')) {
            $tableAndId = ['staff', $route?->parameter('staff'), ['profile_photo_path', 'profile_thumbnail_path']];
        } elseif ($request->hasFile('photo') && str_contains($uri, '/settings/users/')) {
            $tableAndId = ['users', $route?->parameter('user'), ['profile_photo_path', 'profile_thumbnail_path']];
        } elseif ($request->hasFile('photo') && str_ends_with($uri, '/auth/user/photo')) {
            $user = $request->user();
            $paths = [$user?->profile_photo_path, $user?->profile_thumbnail_path];
        } elseif ($request->hasFile('logo') && str_ends_with($uri, '/settings/company-logo')) {
            $paths = [DB::connection('tenant')->table('app_settings')->where('key', 'company_logo_path')->value('value')];
        } elseif ($request->hasFile('image') && str_contains($uri, '/channels/')) {
            $tableAndId = ['channels', $route?->parameter('channel'), ['icon_image_path']];
        } elseif ($request->hasFile('voice') && $route?->parameter('annotation')) {
            $tableAndId = ['beauty_annotations', $route->parameter('annotation'), ['voice_path']];
        }

        if ($tableAndId) {
            [$table, $id, $columns] = $tableAndId;
            $id = is_object($id) ? $id->getKey() : $id;
            if ($id) {
                $record = DB::connection('tenant')->table($table)->where('id', $id)->first($columns);
                foreach ($columns as $column) {
                    $paths[] = $record?->$column;
                }
            }
        }

        $disk = Storage::disk('public');
        $bytes = 0;
        foreach (array_unique(array_filter($paths)) as $path) {
            if ($disk->exists($path)) {
                $bytes += (int) $disk->size($path);
            }
        }
        return $bytes;
    }

    public function databaseBytes(): ?int
    {
        $connection = DB::connection('tenant');
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        return (int) $connection->selectOne(
            'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$connection->getDatabaseName()]
        )->bytes;
    }
}
