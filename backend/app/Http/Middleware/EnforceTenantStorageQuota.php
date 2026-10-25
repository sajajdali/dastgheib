<?php

namespace App\Http\Middleware;

use App\Services\TenantStorageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantStorageQuota
{
    public function __construct(private readonly TenantStorageService $storage)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Recovery endpoints stay available while the rest of the clinic is locked.
        if (
            ($request->is('api/auth/user', 'api/storage', 'api/storage/status') && $request->isMethod('GET'))
            || ($request->is('api/storage/orders') && $request->isMethod('POST'))
            || $request->is('api/subscription', 'api/subscription/*', 'api/store/terms', 'api/service-tickets', 'api/service-tickets/*')
        ) {
            return $next($request);
        }

        $tenantId = (string) tenant('id');
        $summary = $this->storage->summary($tenantId);
        if (! $summary['scanned_at']) {
            $summary = $this->storage->reconcileCurrentTenant();
        }
        if ($summary['used_bytes'] >= $summary['quota_bytes']) {
            return response()->json([
                'message' => 'فضای ذخیره‌سازی سایت تکمیل شده است. برای ادامه، درخواست افزایش فضا ثبت کنید.',
                'code' => 'storage_full',
                'storage' => $summary,
            ], 423);
        }

        $incoming = $this->storage->incomingBytes($request->allFiles());
        $shouldMeasure = $incoming > 0 || $request->isMethod('DELETE');
        if (! $shouldMeasure) {
            return $next($request);
        }

        $central = DB::connection(config('tenancy.database.central_connection'));
        $this->storage->ensureRow($tenantId);

        $failure = null;
        try {
            $response = $central->transaction(function () use ($request, $next, $central, $tenantId, $incoming, &$failure) {
                $row = $central->table('central_tenant_storage')->where('tenant_id', $tenantId)->lockForUpdate()->first();
                if (! $row->scanned_at) {
                    $this->storage->reconcileCurrentTenant();
                    $row = $central->table('central_tenant_storage')->where('tenant_id', $tenantId)->first();
                }

                $replaced = $incoming > 0 ? $this->storage->replaceableBytes($request) : 0;
                if ($incoming > 0 && (int) $row->used_bytes + $incoming - $replaced > $this->storage->quotaBytes($tenantId, $row)) {
                    return response()->json([
                        'message' => 'فضای ذخیره‌سازی سایت کافی نیست. فایل‌های قدیمی را حذف کنید یا فضای بیشتری درخواست دهید.',
                        'code' => 'storage_quota_exceeded',
                        'storage' => $this->storage->summary($tenantId),
                    ], 413);
                }

                try {
                    return $next($request);
                } catch (\Throwable $exception) {
                    $failure = $exception;
                    return response()->noContent();
                } finally {
                    $this->storage->reconcileCurrentTenant();
                }
            });
        } catch (\Throwable $exception) {
            $central->table('central_tenant_storage')->where('tenant_id', $tenantId)
                ->update(['scanned_at' => null]);
            throw $exception;
        }

        if ($failure) {
            throw $failure;
        }
        return $response;
    }
}
